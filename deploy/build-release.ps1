<#
.SYNOPSIS
    Builds a ready-to-upload zip of iShop for shared hosting (FastComet).

.DESCRIPTION
    Takes the last commit (so .env, node_modules, logs and uploads can never
    end up in it), installs production-only PHP packages, builds the
    frontend, publishes the admin panel assets and zips the result with
    forward-slash paths that Linux / cPanel extract correctly.

    Output: releases\ishop-<commit>.zip  (the releases folder is git-ignored)

.EXAMPLE
    powershell -ExecutionPolicy Bypass -File deploy\build-release.ps1
#>
[CmdletBinding()]
param()

$ErrorActionPreference = 'Stop'
$root = Split-Path -Parent $PSScriptRoot
Set-Location $root

function Invoke-Step([string]$label, [scriptblock]$run) {
    Write-Host "==> $label" -ForegroundColor Cyan
    & $run
    if ($LASTEXITCODE -and $LASTEXITCODE -ne 0) { throw "$label failed (exit code $LASTEXITCODE)." }
}

# Only committed work goes out: refuse to build with uncommitted changes.
$dirty = git status --porcelain
if ($dirty) {
    throw "There are uncommitted changes. Commit them first, then build the release.`n$dirty"
}

$commit = (git rev-parse --short HEAD).Trim()
$stage = Join-Path ([IO.Path]::GetTempPath()) "ishop-release-$commit"
$releases = Join-Path $root 'releases'
$zip = Join-Path $releases "ishop-$commit.zip"

if (Test-Path $stage) { Remove-Item $stage -Recurse -Force }
New-Item -ItemType Directory -Force $stage, $releases | Out-Null

Invoke-Step 'Export the last commit' {
    # Through a file: Windows PowerShell pipes between programs as text,
    # which would corrupt the archive.
    $tarFile = "$stage.tar"
    git archive --format=tar -o $tarFile HEAD
    tar -x -f $tarFile -C $stage
    Remove-Item $tarFile -Force
}

Invoke-Step 'Build the frontend' { npm run build }
Copy-Item (Join-Path $root 'public\build') (Join-Path $stage 'public\build') -Recurse

Push-Location $stage
try {
    Invoke-Step 'Install production PHP packages' {
        composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader --no-progress
    }

    # Not needed on the server (frontend sources are already built, tests and
    # tooling are for development only).
    $remove = @('tests', 'node_modules', '.github', 'resources\js', 'resources\css',
                'phpunit.xml', 'phpstan.neon', 'vite.config.ts', 'tsconfig.json',
                'package.json', 'package-lock.json', 'components.json', 'eslint.config.js',
                '.prettierrc', '.prettierignore', '.editorconfig', 'releases')
    foreach ($item in $remove) {
        if (Test-Path $item) { Remove-Item $item -Recurse -Force }
    }

    # Caches from the build machine must never ship (paths differ on the server).
    Get-ChildItem 'bootstrap\cache' -Filter '*.php' | Where-Object Name -ne '.gitignore' | Remove-Item -Force
}
finally {
    Pop-Location
}

Invoke-Step 'Create the zip' {
    if (Test-Path $zip) { Remove-Item $zip -Force }
    # bsdtar writes zip entries with "/" separators (Compress-Archive in
    # Windows PowerShell 5.1 can write "\", which breaks on Linux).
    tar -a -c -f $zip -C $stage .
}

Remove-Item $stage -Recurse -Force

$size = '{0:N1} MB' -f ((Get-Item $zip).Length / 1MB)
Write-Host ""
Write-Host "Release ready: $zip ($size)" -ForegroundColor Green
Write-Host "Upload it to the server and follow deploy\DEPLOY.md."

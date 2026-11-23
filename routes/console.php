<?php

use App\Models\Cart;
use App\Models\Wishlist;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

// Shared hosting: cron runs `schedule:run` every minute.
Schedule::command('model:prune', ['--model' => [Cart::class, Wishlist::class]])->daily()->at('03:30');

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

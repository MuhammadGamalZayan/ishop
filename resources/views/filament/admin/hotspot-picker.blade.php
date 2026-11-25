@php
    // The hotspots repeater sits next to this field: …looks.{slide}.hotspots
    $hotspotsPath = \Illuminate\Support\Str::beforeLast($getStatePath(), '.') . '.hotspots';
@endphp

{{-- Inline styles: Filament's compiled CSS doesn't include our Tailwind classes. --}}
<div
    x-data="{
        hs: $wire.$entangle(@js($hotspotsPath), true),
        active: null,
        keys() { return Object.keys(this.hs || {}) },
        place(event) {
            if (this.active === null) { this.active = this.keys()[0] ?? null }
            if (this.active === null) return
            const box = event.currentTarget.getBoundingClientRect()
            const x = Math.round(((event.clientX - box.left) / box.width) * 1000) / 10
            const y = Math.round(((event.clientY - box.top) / box.height) * 1000) / 10
            this.hs = { ...this.hs, [this.active]: { ...this.hs[this.active], x, y } }
            const next = this.keys()[this.keys().indexOf(this.active) + 1]
            if (next !== undefined) this.active = next
        },
    }"
    style="display: flex; flex-direction: column; gap: 10px"
>
    <p style="font-size: 13px; color: var(--gray-600)">{{ __('admin.home.looks.picker_help') }}</p>

    @if ($image)
        <div style="display: flex; flex-wrap: wrap; gap: 6px">
            <template x-for="(key, i) in keys()" :key="key">
                <button
                    type="button"
                    x-on:click="active = key"
                    x-text="'{{ __('admin.home.looks.dot') }} ' + (i + 1)"
                    :style="active === key
                        ? 'padding: 4px 10px; border-radius: 6px; border: 1px solid var(--primary-600); background: var(--primary-600); color: #fff; font-size: 12px; cursor: pointer'
                        : 'padding: 4px 10px; border-radius: 6px; border: 1px solid var(--gray-300); background: transparent; font-size: 12px; cursor: pointer'"
                ></button>
            </template>
        </div>

        <div x-on:click="place($event)" style="position: relative; max-width: 520px; cursor: crosshair; user-select: none">
            <img src="{{ $image }}" alt="" style="display: block; width: 100%; height: auto; border-radius: 8px" draggable="false">
            <template x-for="(key, i) in keys()" :key="key">
                <span
                    x-text="i + 1"
                    :style="`position: absolute; left: ${hs[key]?.x ?? 50}%; top: ${hs[key]?.y ?? 50}%; transform: translate(-50%, -50%);
                        width: 26px; height: 26px; border-radius: 50%; display: flex; align-items: center; justify-content: center;
                        font-size: 12px; font-weight: 600; pointer-events: none; box-shadow: 0 0 0 6px rgba(255,255,255,.45);
                        background: ${active === key ? 'var(--primary-600)' : '#fff'}; color: ${active === key ? '#fff' : '#111'}`"
                ></span>
            </template>
        </div>
    @else
        <p style="font-size: 13px; color: var(--gray-500)">{{ __('admin.home.looks.upload_first') }}</p>
    @endif
</div>

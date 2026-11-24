<?php

use App\Models\Cart;
use App\Models\Wishlist;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

// Shared hosting: cron runs `schedule:run` every minute.
Schedule::command('model:prune', ['--model' => [Cart::class, Wishlist::class]])->daily()->at('03:30');

// Card orders not paid in time: cancel and restock.
Schedule::command('orders:expire-unpaid')->everyMinute()->withoutOverlapping(5);

// No long-running worker on shared hosting: each minute, work through the
// queue (product photo conversions, mail) and stop when it is empty. Capped
// under a minute so runs never pile up; withoutOverlapping as a second guard.
Schedule::command('queue:work', ['--stop-when-empty', '--max-time=50', '--tries=3', '--quiet'])
    ->everyMinute()
    ->withoutOverlapping(5);

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('tasks:telegram-reminders')
    ->everyFiveMinutes()
    ->withoutOverlapping(10)
    ->appendOutputTo(storage_path('logs/task-reminders.log'));

Schedule::command('opportunities:follow-up')
    ->dailyAt('09:00')
    ->withoutOverlapping(30)
    ->appendOutputTo(storage_path('logs/opportunity-follow-ups.log'));

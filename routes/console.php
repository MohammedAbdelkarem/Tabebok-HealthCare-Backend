<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');


//User
Schedule::command('app:ban-remove')
    ->everyMinute()
    ->runInBackground()
    ->withoutOverlapping();
// Schedule::command('app:delete-unverified')
//     ->everySixHours()
//     ->runInBackground()
//     ->withoutOverlapping();

//Others
// Schedule::command('app:delete-unverified')
//     ->everySixHours()
//     ->runInBackground()
//     ->withoutOverlapping();

Schedule::command('banner:remove')
    ->everyTwoMinutes()
    ->runInBackground()
    ->withoutOverlapping();


Schedule::command('app:delete-otp')
    ->hourly()
    ->runInBackground()
    ->withoutOverlapping();

Schedule::command('app:delete-tokens')
    ->hourly()
    ->runInBackground()
    ->withoutOverlapping();

Schedule::command('app:payment-remover')
    ->everyTwoHours()
    ->runInBackground()
    ->withoutOverlapping();

Schedule::command('app:payment-remover')
    ->everyTwoHours()
    ->runInBackground()
    ->withoutOverlapping();

//Tabibak

//ReservationDailyReminderCommand
Schedule::command('reservation:daily')
    ->hourly()
    ->runInBackground()
    ->withoutOverlapping();

//ReservationHourlyReminderCommand
Schedule::command('reservation:hourly')
    ->everyThirtyMinutes()
    ->runInBackground()
    ->withoutOverlapping();

//MedicineHourlyReminderCommand
Schedule::command('medicine:hourly')
    ->everyThreeMinutes()
    ->runInBackground()
    ->withoutOverlapping();

//ReservationRateReminderCommand
Schedule::command('rate:daily')
    ->hourly()
    ->runInBackground()
    ->withoutOverlapping();

//ResetDailyMedicineReminderCommand
Schedule::command('medicine:reset')
    ->dailyAt('02:00')
    ->runInBackground()
    ->withoutOverlapping();

//StepsReminderCommand
Schedule::command('steps:daily')
    ->dailyAt('18:00')
    ->runInBackground()
    ->withoutOverlapping();

//WaterReminderCommand
Schedule::command('water:hourly')
    ->hourly()
    ->between('9:00', '21:00')
    ->runInBackground()
    ->withoutOverlapping();

//SleepReminderCommand
Schedule::command('sleep:daily')
    ->dailyAt('22:00')
    ->runInBackground()
    ->withoutOverlapping();

//UpdateWeightReminderCommand
Schedule::command('weight:monthly')
    ->monthlyOn(6, '18:30')
    ->runInBackground()
    ->withoutOverlapping();

//MotivationalCommand
Schedule::command('motivational:weekly')
    ->weeklyOn(6, '18:30')
    ->runInBackground()
    ->withoutOverlapping();

//MorningCommand
Schedule::command('morning:daily')
    ->dailyAt('10:00')
    ->runInBackground()
    ->withoutOverlapping();

//EveningCommand
Schedule::command('evening:daily')
    ->dailyAt('19:00')
    ->runInBackground()
    ->withoutOverlapping();

//HealthCommand
Schedule::command('healthCare:weekly')
    ->weeklyOn(4, '17:30')
    ->runInBackground()
    ->withoutOverlapping();

//HealthTipCommand
Schedule::command('healthTip:daily')
    ->cron('0 20 */2 * *')
    ->runInBackground()
    ->withoutOverlapping();




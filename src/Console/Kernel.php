<?php

namespace App\Console;

use Core\Console\Kernel as CoreKernel;
use Core\Console\Scheduling\Schedule;

class Kernel extends CoreKernel
{
    /**
     * Define the application's command schedule.
     *
     * @param  \Core\Console\Scheduling\Schedule  $schedule
     * @return void
     */
    protected function schedule(Schedule $schedule): void
    {
        // $schedule->command('app:health-check')->hourly();
    }
}

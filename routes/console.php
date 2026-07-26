<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('invoices:mark-overdue')->dailyAt('00:05');
Schedule::command('billing:process')->dailyAt('06:00');

<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('schedule:clear-cache')->never();

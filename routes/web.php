<?php

use App\Http\Controllers\DnsCheckerController;
use Illuminate\Support\Facades\Route;

Route::get('/', [DnsCheckerController::class, 'check']);

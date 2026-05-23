<?php

use App\Http\Controllers\EmployeeController;
use App\Http\Controllers\HolidayController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::inertia('dashboard', 'dashboard')->name('dashboard');
    Route::resource('employees', EmployeeController::class);
    Route::resource('holidays', HolidayController::class);
});

require __DIR__.'/settings.php';

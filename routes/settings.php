<?php

use App\Http\Controllers\Settings\AuditLogController;
use App\Http\Controllers\Settings\CompanyController;
use App\Http\Controllers\Settings\NotificationController;
use App\Http\Controllers\Settings\ProfileController;
use App\Http\Controllers\Settings\SecurityController;
use App\Http\Controllers\Settings\UserController;
use Illuminate\Auth\Middleware\RequirePassword;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth'])->group(function () {
    Route::redirect('settings', '/settings/profile');

    Route::get('settings/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('settings/profile', [ProfileController::class, 'update'])->name('profile.update');

    Route::get('settings/notifications', [NotificationController::class, 'edit'])->name('notifications.edit');
});

Route::middleware(['auth', 'role:admin,hr'])->group(function () {
    Route::get('settings/company', [CompanyController::class, 'edit'])->name('company.edit');
    Route::post('settings/company', [CompanyController::class, 'update'])->name('company.update');
});

Route::middleware(['auth', 'role:admin'])->group(function () {
    Route::get('settings/users', [UserController::class, 'index'])->name('users.index');
    Route::post('settings/users', [UserController::class, 'store'])->name('users.store');
    Route::put('settings/users/{user}', [UserController::class, 'update'])->name('users.update');
    Route::delete('settings/users/{user}', [UserController::class, 'destroy'])->name('users.destroy');

    Route::get('settings/audit-logs', [AuditLogController::class, 'index'])->name('audit-logs.index');
});

Route::middleware(['auth', 'verified'])->group(function () {
    // Route::delete('settings/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::get('settings/security', [SecurityController::class, 'edit'])
        ->middleware(RequirePassword::class)
        ->name('security.edit');

    Route::put('settings/password', [SecurityController::class, 'update'])
        ->middleware('throttle:6,1')
        ->name('user-password.update');

    Route::inertia('settings/appearance', 'settings/appearance')->name('appearance.edit');
});

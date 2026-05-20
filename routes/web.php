<?php

use App\Http\Controllers\WelcomeController;
use App\Livewire\Dashboard;
use Illuminate\Support\Facades\Route;

Route::get('/', WelcomeController::class)->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::livewire('dashboard', Dashboard::class)->name('dashboard');
});

require __DIR__.'/billing.php';
require __DIR__.'/settings.php';

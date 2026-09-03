<?php

use App\Http\Controllers\PublicScheduleController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('app');
});

// Enlace de solo lectura que se comparte con el equipo.
Route::get('/ver/{token}', [PublicScheduleController::class, 'page']);

<?php

use App\Http\Controllers\Api\EmployeeController;
use App\Http\Controllers\Api\ScheduleController;
use App\Http\Controllers\Api\ShiftController;
use App\Http\Controllers\Api\ShiftTemplateController;
use App\Http\Controllers\Api\TeamController;
use App\Http\Controllers\PublicScheduleController;
use Illuminate\Support\Facades\Route;

/*
| Contrato REST usado por el frontend (public/assets/horario.js).
| PUT recibe el id en el cuerpo, DELETE en la query (igual que la versión
| PHP plano de la carpeta api/).
*/

Route::get('/teams', [TeamController::class, 'index']);
Route::post('/teams', [TeamController::class, 'store']);
Route::put('/teams', [TeamController::class, 'update']);
Route::delete('/teams', [TeamController::class, 'destroy']);
Route::post('/teams/regenerate-token', [TeamController::class, 'regenerateToken']);

Route::get('/employees', [EmployeeController::class, 'index']);
Route::post('/employees', [EmployeeController::class, 'store']);
Route::put('/employees', [EmployeeController::class, 'update']);
Route::delete('/employees', [EmployeeController::class, 'destroy']);

Route::get('/shifts', [ShiftController::class, 'index']);
Route::post('/shifts', [ShiftController::class, 'store']);
Route::put('/shifts', [ShiftController::class, 'update']);
Route::delete('/shifts', [ShiftController::class, 'destroy']);
Route::post('/shifts/repeat', [ScheduleController::class, 'repeat']);

// Plantilla semanal por empleado (Lun–Vie / Sáb–Dom) y generación del mes.
Route::get('/templates', [ShiftTemplateController::class, 'index']);
Route::post('/templates', [ShiftTemplateController::class, 'upsert']);
Route::delete('/templates', [ShiftTemplateController::class, 'destroy']);
Route::post('/schedule/generate', [ScheduleController::class, 'generate']);

// Datos de solo lectura para el enlace que se comparte con el equipo.
Route::get('/ver/{token}/data', [PublicScheduleController::class, 'data']);

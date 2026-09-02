<?php

use App\Http\Controllers\Api\EmployeeController;
use App\Http\Controllers\Api\ShiftController;
use Illuminate\Support\Facades\Route;

/*
| Contrato REST usado por el frontend (resources/views/app.blade.php).
| Se mantiene 1:1 con la versión PHP plano (carpeta api/) para no cambiar
| la lógica del cliente: PUT recibe el id en el cuerpo, DELETE en la query.
*/

Route::get('/employees', [EmployeeController::class, 'index']);
Route::post('/employees', [EmployeeController::class, 'store']);
Route::put('/employees', [EmployeeController::class, 'update']);
Route::delete('/employees', [EmployeeController::class, 'destroy']);

Route::get('/shifts', [ShiftController::class, 'index']);
Route::post('/shifts', [ShiftController::class, 'store']);
Route::put('/shifts', [ShiftController::class, 'update']);
Route::delete('/shifts', [ShiftController::class, 'destroy']);

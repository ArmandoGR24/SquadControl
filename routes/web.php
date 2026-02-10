<?php

use App\Http\Controllers\TareasController;
use App\Http\Controllers\UsuariosController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Laravel\Fortify\Features;

Route::get('/', function () {
    return Inertia::render('Welcome', [
        'canRegister' => Features::enabled(Features::registration()),
    ]);
})->name('home');

Route::get('dashboard', function () {
    return Inertia::render('Dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::get('usuarios', [UsuariosController::class, 'index'])
    ->middleware(['auth'])
    ->name('usuarios');

Route::post('usuarios', [UsuariosController::class, 'store'])
    ->middleware(['auth'])
    ->name('usuarios.store');

Route::put('usuarios/{user}', [UsuariosController::class, 'update'])
    ->middleware(['auth'])
    ->name('usuarios.update');

Route::delete('usuarios/{user}', [UsuariosController::class, 'destroy'])
    ->middleware(['auth'])
    ->name('usuarios.destroy');

Route::get('tareas', [TareasController::class, 'index'])
    ->middleware(['auth'])
    ->name('tareas');

Route::post('tareas', [TareasController::class, 'store'])
    ->middleware(['auth'])
    ->name('tareas.store');

Route::put('tareas/{task}', [TareasController::class, 'update'])
    ->middleware(['auth'])
    ->name('tareas.update');

Route::delete('tareas/{task}', [TareasController::class, 'destroy'])
    ->middleware(['auth'])
    ->name('tareas.destroy');

Route::post('tareas/{task}/evidencias', [TareasController::class, 'storeEvidence'])
    ->middleware(['auth'])
    ->name('tareas.evidencias.store');

Route::patch('tareas/{task}/estado', [TareasController::class, 'updateStatus'])
    ->middleware(['auth'])
    ->name('tareas.estado');

Route::get('mis-tareas', [TareasController::class, 'mine'])
    ->middleware(['auth'])
    ->name('tareas.mis');

Route::get('mis-tareas/{task}', [TareasController::class, 'showMine'])
    ->middleware(['auth'])
    ->name('tareas.mis.show');

Route::get('checkin', [\App\Http\Controllers\CheckinController::class, 'index'])
    ->middleware(['auth'])
    ->name('checkin');

Route::post('checkin/entrada', [\App\Http\Controllers\CheckinController::class, 'checkIn'])
    ->middleware(['auth'])
    ->name('checkin.entrada');

Route::post('checkin/salida', [\App\Http\Controllers\CheckinController::class, 'checkOut'])
    ->middleware(['auth'])
    ->name('checkin.salida');

Route::get('checkin/historial', [\App\Http\Controllers\CheckinController::class, 'history'])
    ->middleware(['auth'])
    ->name('checkin.historial');

Route::get('checkins-admin', [\App\Http\Controllers\CheckinController::class, 'adminIndex'])
    ->middleware(['auth'])
    ->name('checkins.admin');

require __DIR__.'/settings.php';

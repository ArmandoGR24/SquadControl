<?php

use App\Http\Controllers\PublicStorageController;
use App\Http\Controllers\TareasController;
use App\Http\Controllers\UsuariosController;
use App\Models\Checkin;
use App\Models\Task;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Laravel\Fortify\Features;

Route::get('/', function () {
    return Inertia::render('Welcome', [
        'canRegister' => Features::enabled(Features::registration()),
        'canResetPassword' => Features::enabled(Features::resetPasswords()),
    ]);
})->name('home');

Route::get('media/{path}', PublicStorageController::class)
    ->where('path', '.*')
    ->name('public.media.fallback');

Route::get('dashboard', function (Request $request) {
    $user = $request->user();
    $activeTasks = Task::query()
        ->where('status', '!=', 'Completada')
        ->count();
    $inReviewTasks = Task::query()
        ->where('status', 'En revisión')
        ->count();
    $completedTasks = Task::query()
        ->where('status', 'Completada')
        ->count();
    $checkinsToday = Checkin::query()
        ->whereDate('check_in_time', Carbon::today())
        ->count();
    $notifications = $user
        ? $user->notifications()
            ->latest()
            ->limit(8)
            ->get()
            ->map(fn ($notification) => [
                'id' => $notification->id,
                'type' => $notification->data['type'] ?? $notification->type,
                'data' => $notification->data,
                'created_at' => optional($notification->created_at)->toDateTimeString(),
                'read_at' => optional($notification->read_at)->toDateTimeString(),
            ])
            ->all()
        : [];

    return Inertia::render('Dashboard', [
        'stats' => [
            'activeTasks' => $activeTasks,
            'inReviewTasks' => $inReviewTasks,
            'completedTasks' => $completedTasks,
            'checkinsToday' => $checkinsToday,
        ],
        'notifications' => $notifications,
    ]);
})->middleware(['auth', 'verified', 'role:Admin,RH,Supervisor'])->name('dashboard');

Route::delete('notifications/{notification}', function (Request $request, string $notification) {
    $user = $request->user();

    if ($user) {
        $user->notifications()->where('id', $notification)->delete();
    }

    return back();
})->middleware(['auth'])->name('notifications.destroy');

Route::delete('notifications', function (Request $request) {
    $user = $request->user();
    $type = $request->input('type');

    if (! $user) {
        return back();
    }

    $query = $user->notifications();

    if ($type) {
        $query->whereJsonContains('data->type', $type);
    }

    $query->delete();

    return back();
})->middleware(['auth'])->name('notifications.destroy-all');

Route::get('usuarios', [UsuariosController::class, 'index'])
    ->middleware(['auth', 'role:Admin'])
    ->name('usuarios');

Route::post('usuarios', [UsuariosController::class, 'store'])
    ->middleware(['auth', 'role:Admin'])
    ->name('usuarios.store');

Route::put('usuarios/{user}', [UsuariosController::class, 'update'])
    ->middleware(['auth', 'role:Admin'])
    ->name('usuarios.update');

Route::delete('usuarios/{user}', [UsuariosController::class, 'destroy'])
    ->middleware(['auth', 'role:Admin'])
    ->name('usuarios.destroy');

Route::get('tareas', [TareasController::class, 'index'])
    ->middleware(['auth', 'role:Admin,RH,Supervisor'])
    ->name('tareas');

Route::get('materiales', [TareasController::class, 'materialsIndex'])
    ->middleware(['auth', 'role:Admin,RH,Supervisor'])
    ->name('materiales');

Route::get('materiales/{task}', [TareasController::class, 'materialsShow'])
    ->middleware(['auth', 'role:Admin,RH,Supervisor'])
    ->name('materiales.show');

Route::post('tareas', [TareasController::class, 'store'])
    ->middleware(['auth', 'role:Admin,RH,Supervisor'])
    ->name('tareas.store');

Route::put('tareas/{task}', [TareasController::class, 'update'])
    ->middleware(['auth', 'role:Admin,RH,Supervisor'])
    ->name('tareas.update');

Route::delete('tareas/{task}', [TareasController::class, 'destroy'])
    ->middleware(['auth', 'role:Admin,RH,Supervisor'])
    ->name('tareas.destroy');

Route::post('tareas/{task}/evidencias', [TareasController::class, 'storeEvidence'])
    ->middleware(['auth'])
    ->name('tareas.evidencias.store');

Route::patch('tareas/{task}/estado', [TareasController::class, 'updateStatus'])
    ->middleware(['auth'])
    ->name('tareas.estado');

Route::patch('tareas/{task}/revision', [TareasController::class, 'review'])
    ->middleware(['auth', 'role:Admin,RH,Supervisor'])
    ->name('tareas.revision');

Route::patch('tareas/{task}/materiales', [TareasController::class, 'updateMaterials'])
    ->middleware(['auth', 'role:Admin,RH,Supervisor'])
    ->name('tareas.materiales.update');

Route::get('mis-tareas', [TareasController::class, 'mine'])
    ->middleware(['auth'])
    ->name('tareas.mis');

Route::get('mis-materiales', [TareasController::class, 'materialsMine'])
    ->middleware(['auth'])
    ->name('materiales.mis');

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

// Firebase Cloud Messaging Routes
Route::get('fcm/debug', function () {
    return Inertia::render('FCMDebug');
})->middleware(['auth', 'dev-mode'])->name('fcm.debug');

Route::post('fcm/token', [\App\Http\Controllers\FirebaseController::class, 'saveToken'])
    ->middleware(['auth'])
    ->name('fcm.token');

Route::get('fcm/my-tokens', [\App\Http\Controllers\FirebaseController::class, 'myTokens'])
    ->middleware(['auth', 'dev-mode'])
    ->name('fcm.my-tokens');

Route::post('fcm/test', [\App\Http\Controllers\FirebaseController::class, 'sendTestNotification'])
    ->middleware(['auth', 'dev-mode'])
    ->name('fcm.test');

Route::post('fcm/broadcast', [\App\Http\Controllers\FirebaseController::class, 'sendBroadcast'])
    ->middleware(['auth', 'role:Admin,RH,Supervisor'])
    ->name('fcm.broadcast');

Route::post('fcm/universal-send', [\App\Http\Controllers\UniversalNotificationController::class, 'send'])
    ->middleware(['auth', 'role:Admin,RH,Supervisor'])
    ->name('fcm.universal-send');

Route::post('fcm/roles-send', [\App\Http\Controllers\UniversalNotificationController::class, 'sendByRoles'])
    ->middleware(['auth', 'role:Admin,RH,Supervisor'])
    ->name('fcm.roles-send');

require __DIR__.'/settings.php';

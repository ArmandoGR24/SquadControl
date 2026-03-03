<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Models\UserFcmToken;
use App\Services\FirebaseService;
use Illuminate\Console\Command;

class TestFirebaseCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'firebase:test 
                            {--user= : ID del usuario para enviar notificación de prueba}
                            {--broadcast : Enviar notificación a todos los usuarios}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Prueba el servicio de Firebase Cloud Messaging';

    protected $firebaseService;

    public function __construct(FirebaseService $firebaseService)
    {
        parent::__construct();
        $this->firebaseService = $firebaseService;
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('🔥 Probando Firebase Cloud Messaging...');
        $this->newLine();

        // Verificar archivo de credenciales
        $serviceAccountPath = storage_path('app/firebase-service-account.json');

        if (! file_exists($serviceAccountPath)) {
            $this->error('❌ Error: No se encontró el archivo de Service Account.');
            $this->warn('📥 Descárgalo desde Firebase Console y colócalo en:');
            $this->line("   {$serviceAccountPath}");
            $this->newLine();
            $this->info('📚 Ver guía completa: TESTING_GUIDE.md');

            return 1;
        }

        $this->info('✅ Service Account encontrado');

        // Verificar usuarios con token
        $usersWithToken = User::whereHas('fcmTokens')->count();

        if ($usersWithToken === 0) {
            $this->warn('⚠️  No hay usuarios con token FCM registrado.');
            $this->info('💡 Inicia sesión en la app web y acepta los permisos de notificación.');

            return 1;
        }

        $this->info("✅ {$usersWithToken} usuario(s) con token FCM");
        $this->newLine();

        // Opción: Broadcast
        if ($this->option('broadcast')) {
            return $this->sendBroadcast();
        }

        // Opción: Usuario específico
        if ($userId = $this->option('user')) {
            return $this->sendToUser($userId);
        }

        // Por defecto: Mostrar menú
        return $this->showMenu();
    }

    protected function showMenu()
    {
        $this->info('📋 Usuarios con tokens FCM:');

        $users = User::whereHas('fcmTokens')
            ->get(['id', 'name', 'email', 'role'])
            ->map(function ($user) {
                return [
                    'ID' => $user->id,
                    'Nombre' => $user->name,
                    'Email' => $user->email,
                    'Rol' => $user->role,
                ];
            });

        $this->table(
            ['ID', 'Nombre', 'Email', 'Rol'],
            $users->toArray()
        );

        $this->newLine();
        $this->info('🎯 Para enviar notificación de prueba:');
        $this->line('   php artisan firebase:test --user=ID');
        $this->newLine();
        $this->info('📢 Para enviar broadcast a todos:');
        $this->line('   php artisan firebase:test --broadcast');

        return 0;
    }

    protected function sendToUser($userId)
    {
        $user = User::find($userId);

        if (! $user) {
            $this->error("❌ Usuario con ID {$userId} no encontrado.");

            return 1;
        }

        $tokens = $user->fcmTokens()
            ->pluck('token')
            ->filter()
            ->unique()
            ->values()
            ->toArray();

        if (empty($tokens)) {
            $this->error("❌ El usuario {$user->name} no tiene token FCM.");
            $this->warn('💡 Debe iniciar sesión en la app y aceptar permisos de notificación.');

            return 1;
        }

        $this->info("📤 Enviando notificación de prueba a: {$user->name}...");

        $result = $this->firebaseService->sendMulticast(
            $tokens,
            '🔔 Notificación de Prueba',
            'Esta es una notificación de prueba desde SquadControl CLI',
            [
                'type' => 'test',
                'source' => 'artisan',
                'timestamp' => now()->toIso8601String(),
            ]
        );

        if ($result['success']) {
            $this->info('✅ ¡Notificación enviada exitosamente!');
            $this->line("   Usuario: {$user->name} ({$user->email})");
        } else {
            $this->error('❌ Error al enviar notificación:');
            $this->warn("   {$result['message']}");

            return 1;
        }

        return 0;
    }

    protected function sendBroadcast()
    {
        $users = User::whereHas('fcmTokens')->get();
        $tokens = UserFcmToken::query()
            ->pluck('token')
            ->filter()
            ->unique()
            ->values()
            ->toArray();

        if (empty($tokens)) {
            $this->error('❌ No hay usuarios con tokens FCM para enviar broadcast.');

            return 1;
        }

        $this->warn("📢 ¿Enviar notificación a {$users->count()} usuario(s)?");

        if (! $this->confirm('¿Continuar?', true)) {
            $this->info('❌ Operación cancelada.');

            return 0;
        }

        $this->info('📤 Enviando broadcast...');

        $result = $this->firebaseService->sendMulticast(
            $tokens,
            '📢 Notificación Masiva de Prueba',
            'Este es un mensaje de prueba enviado a todos los usuarios desde CLI',
            [
                'type' => 'broadcast',
                'source' => 'artisan',
                'timestamp' => now()->toIso8601String(),
            ]
        );

        if ($result['success']) {
            $this->info('✅ ¡Broadcast enviado exitosamente!');
            $this->line("   Enviadas correctamente: {$result['successful']}");

            if ($result['failed'] > 0) {
                $this->warn("   Fallidas: {$result['failed']}");
            }
        } else {
            $this->error('❌ Error al enviar broadcast:');
            $this->warn("   {$result['message']}");

            return 1;
        }

        return 0;
    }
}

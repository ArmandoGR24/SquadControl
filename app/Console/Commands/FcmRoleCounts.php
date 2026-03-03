<?php

namespace App\Console\Commands;

use App\Models\UserFcmToken;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class FcmRoleCounts extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'fcm:role-counts {roles?* : Lista de roles a consultar}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Muestra conteos de tokens FCM y usuarios por rol';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $roles = $this->argument('roles');

        $query = UserFcmToken::query()
            ->join('users', 'users.id', '=', 'user_fcm_tokens.user_id');

        if (! empty($roles)) {
            $query->whereIn('users.role', $roles);
        }

        $rows = $query
            ->select(
                'users.role',
                DB::raw('COUNT(DISTINCT user_fcm_tokens.user_id) as users'),
                DB::raw('COUNT(*) as tokens')
            )
            ->groupBy('users.role')
            ->orderBy('users.role')
            ->get();

        if ($rows->isEmpty()) {
            $this->warn('No hay tokens FCM registrados para los roles indicados.');

            return 0;
        }

        $this->table(['Rol', 'Usuarios', 'Tokens'], $rows->toArray());

        return 0;
    }
}

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('user_fcm_tokens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('token', 512)->unique();
            $table->string('device_name')->nullable();
            $table->text('device_user_agent')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'last_used_at']);
        });

        if (Schema::hasColumn('users', 'fcm_token')) {
            DB::table('users')
                ->select('id', 'fcm_token')
                ->whereNotNull('fcm_token')
                ->orderBy('id')
                ->chunkById(100, function ($users): void {
                    $now = now();
                    $rows = [];

                    foreach ($users as $user) {
                        $token = trim((string) $user->fcm_token);

                        if ($token === '') {
                            continue;
                        }

                        $rows[] = [
                            'user_id' => $user->id,
                            'token' => $token,
                            'last_used_at' => $now,
                            'created_at' => $now,
                            'updated_at' => $now,
                        ];
                    }

                    if (! empty($rows)) {
                        DB::table('user_fcm_tokens')->upsert(
                            $rows,
                            ['token'],
                            ['user_id', 'last_used_at', 'updated_at']
                        );
                    }
                }, 'id');

            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn('fcm_token');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasColumn('users', 'fcm_token')) {
            Schema::table('users', function (Blueprint $table) {
                $table->string('fcm_token', 512)->nullable()->after('remember_token');
            });
        }

        $rows = DB::table('user_fcm_tokens')
            ->select('user_id', 'token')
            ->orderBy('id')
            ->get();

        foreach ($rows as $row) {
            DB::table('users')
                ->where('id', $row->user_id)
                ->whereNull('fcm_token')
                ->update(['fcm_token' => $row->token]);
        }

        Schema::dropIfExists('user_fcm_tokens');
    }
};

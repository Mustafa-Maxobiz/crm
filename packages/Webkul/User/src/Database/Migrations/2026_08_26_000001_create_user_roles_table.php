<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Keeps users.role_id intact. Backfills user_roles from the existing 1:1 assignment.
     */
    public function up(): void
    {
        Schema::create('user_roles', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('user_id');
            $table->unsignedInteger('role_id');
            $table->timestamps();

            $table->unique(['user_id', 'role_id']);
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('role_id')->references('id')->on('roles')->cascadeOnDelete();
            $table->index('role_id');
        });

        $now = now();

        DB::table('users')
            ->whereNotNull('role_id')
            ->orderBy('id')
            ->select(['id', 'role_id'])
            ->chunkById(200, function ($users) use ($now) {
                $rows = [];

                foreach ($users as $user) {
                    $rows[] = [
                        'user_id'    => (int) $user->id,
                        'role_id'    => (int) $user->role_id,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }

                if (! empty($rows)) {
                    DB::table('user_roles')->insertOrIgnore($rows);
                }
            });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_roles');
    }
};

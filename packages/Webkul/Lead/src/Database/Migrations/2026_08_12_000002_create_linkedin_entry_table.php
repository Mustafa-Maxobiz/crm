<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('linkedin_entry', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('user_id');
            $table->string('name');
            $table->string('url', 2048);
            $table->string('status')->default('pending');
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->index(['user_id', 'status']);
        });

        $this->grantLgePermissions();
    }

    public function down(): void
    {
        Schema::dropIfExists('linkedin_entry');
    }

    protected function grantLgePermissions(): void
    {
        $permissionsToAdd = [
            'linkedin_entries',
            'linkedin_entries.create',
            'linkedin_entries.edit',
        ];

        $roles = DB::table('roles')
            ->whereRaw('LOWER(name) = ?', ['lge'])
            ->get(['id', 'permission_type', 'permissions']);

        foreach ($roles as $role) {
            if ($role->permission_type === 'all') {
                continue;
            }

            $permissions = json_decode($role->permissions ?? '[]', true);

            if (! is_array($permissions)) {
                $permissions = [];
            }

            DB::table('roles')
                ->where('id', $role->id)
                ->update([
                    'permissions' => json_encode(array_values(array_unique(array_merge($permissions, $permissionsToAdd)))),
                    'updated_at'  => now(),
                ]);
        }
    }
};

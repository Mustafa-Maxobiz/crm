<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('user_roles') || Schema::hasColumn('user_roles', 'is_tl')) {
            return;
        }

        Schema::table('user_roles', function (Blueprint $table) {
            $table->boolean('is_tl')->default(false)->after('role_id');
            $table->index(['role_id', 'is_tl'], 'user_roles_role_tl_index');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('user_roles') || ! Schema::hasColumn('user_roles', 'is_tl')) {
            return;
        }

        Schema::table('user_roles', function (Blueprint $table) {
            $table->dropIndex('user_roles_role_tl_index');
            $table->dropColumn('is_tl');
        });
    }
};

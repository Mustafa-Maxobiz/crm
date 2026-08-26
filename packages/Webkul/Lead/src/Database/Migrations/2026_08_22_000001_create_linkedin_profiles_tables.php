<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('linkedin_profiles', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('profile_url', 2048);
            $table->string('profile_url_normalized', 512)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique('profile_url_normalized');
            $table->index('is_active');
        });

        Schema::create('linkedin_profile_user', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('linkedin_profile_id');
            $table->unsignedInteger('user_id');
            $table->timestamps();

            $table->foreign('linkedin_profile_id')
                ->references('id')
                ->on('linkedin_profiles')
                ->cascadeOnDelete();

            $table->foreign('user_id')
                ->references('id')
                ->on('users')
                ->cascadeOnDelete();

            $table->unique(['linkedin_profile_id', 'user_id']);
            $table->index('user_id');
            $table->index('linkedin_profile_id');
        });

        if (Schema::hasTable('linkedin_entry') && ! Schema::hasColumn('linkedin_entry', 'linkedin_profile_id')) {
            Schema::table('linkedin_entry', function (Blueprint $table) {
                $table->unsignedBigInteger('linkedin_profile_id')->nullable()->after('user_id');

                $table->foreign('linkedin_profile_id')
                    ->references('id')
                    ->on('linkedin_profiles')
                    ->nullOnDelete();

                $table->index('linkedin_profile_id');
                $table->index(['user_id', 'linkedin_profile_id', 'status']);
            });
        }

        if (Schema::hasTable('leads') && ! Schema::hasColumn('leads', 'linkedin_profile_id')) {
            Schema::table('leads', function (Blueprint $table) {
                $table->unsignedBigInteger('linkedin_profile_id')->nullable()->after('source_link');

                $table->foreign('linkedin_profile_id')
                    ->references('id')
                    ->on('linkedin_profiles')
                    ->nullOnDelete();

                $table->index('linkedin_profile_id');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('leads') && Schema::hasColumn('leads', 'linkedin_profile_id')) {
            Schema::table('leads', function (Blueprint $table) {
                $table->dropForeign(['linkedin_profile_id']);
                $table->dropIndex(['linkedin_profile_id']);
                $table->dropColumn('linkedin_profile_id');
            });
        }

        if (Schema::hasTable('linkedin_entry') && Schema::hasColumn('linkedin_entry', 'linkedin_profile_id')) {
            Schema::table('linkedin_entry', function (Blueprint $table) {
                $table->dropForeign(['linkedin_profile_id']);
                $table->dropIndex(['linkedin_profile_id']);
                $table->dropIndex(['user_id', 'linkedin_profile_id', 'status']);
                $table->dropColumn('linkedin_profile_id');
            });
        }

        Schema::dropIfExists('linkedin_profile_user');
        Schema::dropIfExists('linkedin_profiles');
    }
};

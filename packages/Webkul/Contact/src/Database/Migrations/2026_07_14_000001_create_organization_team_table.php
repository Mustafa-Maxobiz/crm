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
        if (! Schema::hasTable('organization_team')) {
            Schema::create('organization_team', function (Blueprint $table) {
                $table->unsignedInteger('organization_id');
                $table->unsignedInteger('team_id');

                $table->primary(['organization_id', 'team_id']);
                $table->foreign('organization_id')->references('id')->on('organizations')->onDelete('cascade');
                $table->foreign('team_id')->references('id')->on('teams')->onDelete('cascade');
            });
        }

        if (Schema::hasColumn('teams', 'organization_id')) {
            DB::table('teams')
                ->whereNotNull('organization_id')
                ->orderBy('id')
                ->get(['id', 'organization_id'])
                ->each(function ($team) {
                    DB::table('organization_team')->insertOrIgnore([
                        'organization_id' => $team->organization_id,
                        'team_id'         => $team->id,
                    ]);
            });

            Schema::table('teams', function (Blueprint $table) {
                $table->dropForeign(['organization_id']);
                $table->dropUnique('teams_organization_id_name_unique');
                $table->dropColumn('organization_id');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasColumn('teams', 'organization_id')) {
            Schema::table('teams', function (Blueprint $table) {
                $table->unsignedInteger('organization_id')->nullable()->after('description');
            });

            DB::table('teams')
                ->leftJoin('organization_team', 'organization_team.team_id', '=', 'teams.id')
                ->select('teams.id', DB::raw('MIN(organization_team.organization_id) as organization_id'))
                ->groupBy('teams.id')
                ->get()
                ->each(function ($team) {
                    DB::table('teams')
                        ->where('id', $team->id)
                        ->update(['organization_id' => $team->organization_id]);
                });

            Schema::table('teams', function (Blueprint $table) {
                $table->foreign('organization_id')->references('id')->on('organizations')->onDelete('cascade');
            });
        }

        Schema::dropIfExists('organization_team');
    }
};

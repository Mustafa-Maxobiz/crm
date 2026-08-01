<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add nullable company FK on leads and replace text "companies"/"title" attribute
     * with an organization_id lookup attribute.
     */
    public function up(): void
    {
        if (! Schema::hasColumn('leads', 'organization_id')) {
            Schema::table('leads', function (Blueprint $table) {
                $table->unsignedInteger('organization_id')->nullable()->after('person_id');
                $table->foreign('organization_id')
                    ->references('id')
                    ->on('organizations')
                    ->nullOnDelete();
                $table->index('organization_id');
            });
        }

        // Backfill from linked person's company.
        DB::statement('
            UPDATE leads
            INNER JOIN persons ON persons.id = leads.person_id
            SET leads.organization_id = persons.organization_id
            WHERE leads.organization_id IS NULL
              AND persons.organization_id IS NOT NULL
        ');

        // Match free-text title/company name to an organization when still empty.
        DB::statement('
            UPDATE leads
            INNER JOIN organizations ON organizations.name = leads.title
            SET leads.organization_id = organizations.id
            WHERE leads.organization_id IS NULL
              AND leads.title IS NOT NULL
              AND leads.title != ""
        ');

        // Replace lead text attribute companies/title with organization_id lookup.
        $companiesAttribute = DB::table('attributes')
            ->where('entity_type', 'leads')
            ->whereIn('code', ['companies', 'title'])
            ->orderByRaw("CASE WHEN code = 'companies' THEN 0 ELSE 1 END")
            ->first();

        if ($companiesAttribute) {
            DB::table('attribute_values')
                ->where('attribute_id', $companiesAttribute->id)
                ->delete();

            DB::table('attributes')
                ->where('id', $companiesAttribute->id)
                ->update([
                    'code'        => 'organization_id',
                    'name'        => 'Company Name',
                    'type'        => 'lookup',
                    'lookup_type' => 'organizations',
                    'validation'  => null,
                    'is_required' => 0,
                    'updated_at'  => now(),
                ]);
        } elseif (! DB::table('attributes')->where('entity_type', 'leads')->where('code', 'organization_id')->exists()) {
            DB::table('attributes')->insert([
                'code'            => 'organization_id',
                'name'            => 'Company Name',
                'type'            => 'lookup',
                'entity_type'     => 'leads',
                'lookup_type'     => 'organizations',
                'validation'      => null,
                'sort_order'      => 1,
                'is_required'     => 0,
                'is_unique'       => 0,
                'quick_add'       => 1,
                'is_user_defined' => 0,
                'created_at'      => now(),
                'updated_at'      => now(),
            ]);
        }

        // Remove any leftover duplicate title/companies lead attributes.
        DB::table('attributes')
            ->where('entity_type', 'leads')
            ->whereIn('code', ['companies', 'title'])
            ->where('code', '!=', 'organization_id')
            ->delete();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $organizationAttribute = DB::table('attributes')
            ->where('entity_type', 'leads')
            ->where('code', 'organization_id')
            ->first();

        if ($organizationAttribute) {
            DB::table('attribute_values')
                ->where('attribute_id', $organizationAttribute->id)
                ->delete();

            DB::table('attributes')
                ->where('id', $organizationAttribute->id)
                ->update([
                    'code'        => 'companies',
                    'name'        => 'Company Name',
                    'type'        => 'text',
                    'lookup_type' => null,
                    'is_required' => 1,
                    'updated_at'  => now(),
                ]);
        }

        if (Schema::hasColumn('leads', 'organization_id')) {
            Schema::table('leads', function (Blueprint $table) {
                $table->dropForeign(['organization_id']);
                $table->dropIndex(['organization_id']);
                $table->dropColumn('organization_id');
            });
        }
    }
};

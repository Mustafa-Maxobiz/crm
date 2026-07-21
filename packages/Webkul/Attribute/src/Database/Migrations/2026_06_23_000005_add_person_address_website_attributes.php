<?php

use Carbon\Carbon;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $now = Carbon::now();

        $attributes = [
            [
                'code'            => 'address',
                'name'            => trans('installer::app.seeders.attributes.persons.address'),
                'type'            => 'address',
                'entity_type'     => 'persons',
                'lookup_type'     => null,
                'validation'      => null,
                'sort_order'      => '7',
                'is_required'     => '0',
                'is_unique'       => '0',
                'quick_add'       => '1',
                'is_user_defined' => '0',
                'created_at'      => $now,
                'updated_at'      => $now,
            ],
            [
                'code'            => 'website',
                'name'            => trans('installer::app.seeders.attributes.persons.website'),
                'type'            => 'text',
                'entity_type'     => 'persons',
                'lookup_type'     => null,
                'validation'      => 'url',
                'sort_order'      => '8',
                'is_required'     => '0',
                'is_unique'       => '0',
                'quick_add'       => '1',
                'is_user_defined' => '0',
                'created_at'      => $now,
                'updated_at'      => $now,
            ],
        ];

        foreach ($attributes as $attribute) {
            $exists = DB::table('attributes')
                ->where('entity_type', 'persons')
                ->where('code', $attribute['code'])
                ->exists();

            if (! $exists) {
                DB::table('attributes')->insert($attribute);
            }
        }

        DB::table('attributes')
            ->where('entity_type', 'persons')
            ->whereIn('code', [
                'name',
                'emails',
                'contact_numbers',
                'job_title',
                'organization_id',
                'address',
                'website',
            ])
            ->update(['is_required' => 0]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('attributes')
            ->where('entity_type', 'persons')
            ->whereIn('code', ['address', 'website'])
            ->delete();
    }
};

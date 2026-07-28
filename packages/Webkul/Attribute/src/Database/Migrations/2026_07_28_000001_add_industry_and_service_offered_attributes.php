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

        $this->createSelectAttribute(
            code: 'industry',
            name: 'Industry',
            sortOrder: '5.1',
            options: [
                'Healthcare',
                'Real Estate',
                'Legal',
                'Construction',
                'IT / Software',
                'Finance',
                'Education',
                'Retail',
                'Hospitality',
                'Other',
            ],
            now: $now,
        );

        $this->createSelectAttribute(
            code: 'service_offered',
            name: 'Service Offered',
            sortOrder: '5.2',
            options: [
                'Website Development',
                'Social Media',
                'SEO',
                'Branding',
                'Paid Ads',
                'Email Marketing',
                'Content Marketing',
                'Other',
            ],
            now: $now,
        );
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        foreach (['industry', 'service_offered'] as $code) {
            $attributeId = DB::table('attributes')
                ->where('code', $code)
                ->where('entity_type', 'leads')
                ->value('id');

            if (! $attributeId) {
                continue;
            }

            DB::table('attribute_values')
                ->where('attribute_id', $attributeId)
                ->delete();

            DB::table('attribute_options')
                ->where('attribute_id', $attributeId)
                ->delete();

            DB::table('attributes')
                ->where('id', $attributeId)
                ->delete();
        }
    }

    protected function createSelectAttribute(string $code, string $name, string $sortOrder, array $options, Carbon $now): void
    {
        $existingAttribute = DB::table('attributes')
            ->where('code', $code)
            ->where('entity_type', 'leads')
            ->first();

        if (! $existingAttribute) {
            DB::table('attributes')->insert([
                'code'            => $code,
                'name'            => $name,
                'type'            => 'select',
                'entity_type'     => 'leads',
                'lookup_type'     => null,
                'validation'      => null,
                'sort_order'      => $sortOrder,
                'is_required'     => '0',
                'is_unique'       => '0',
                'quick_add'       => '1',
                'is_user_defined' => '0',
                'created_at'      => $now,
                'updated_at'      => $now,
            ]);
        }

        $attributeId = DB::table('attributes')
            ->where('code', $code)
            ->where('entity_type', 'leads')
            ->value('id');

        if (! $attributeId) {
            return;
        }

        $existingOptions = DB::table('attribute_options')
            ->where('attribute_id', $attributeId)
            ->count();

        if ($existingOptions > 0) {
            return;
        }

        $rows = [];

        foreach (array_values($options) as $index => $optionName) {
            $rows[] = [
                'attribute_id' => $attributeId,
                'name'         => $optionName,
                'sort_order'   => $index + 1,
            ];
        }

        DB::table('attribute_options')->insert($rows);
    }
};

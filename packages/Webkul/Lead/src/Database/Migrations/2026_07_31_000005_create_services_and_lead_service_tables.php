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
        Schema::create('services', function (Blueprint $table) {
            $table->increments('id');
            $table->string('name');
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            $table->unique('name');
        });

        Schema::create('lead_service', function (Blueprint $table) {
            $table->unsignedInteger('lead_id');
            $table->unsignedInteger('service_id');

            $table->foreign('lead_id')->references('id')->on('leads')->onDelete('cascade');
            $table->foreign('service_id')->references('id')->on('services')->onDelete('cascade');

            $table->unique(['lead_id', 'service_id']);
        });

        $this->migrateFromAttributeSystem();
        $this->removeServiceOfferedAttribute();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('lead_service');
        Schema::dropIfExists('services');
    }

    /**
     * Copy existing attribute options/values into services + pivot.
     */
    protected function migrateFromAttributeSystem(): void
    {
        $attribute = DB::table('attributes')
            ->where('code', 'service_offered')
            ->where('entity_type', 'leads')
            ->first();

        $now = now();

        if (! $attribute) {
            $this->seedDefaultServices($now);

            return;
        }

        $options = DB::table('attribute_options')
            ->where('attribute_id', $attribute->id)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get(['id', 'name', 'sort_order']);

        $optionToServiceId = [];

        foreach ($options as $option) {
            $serviceId = DB::table('services')->insertGetId([
                'name'       => $option->name,
                'sort_order' => (int) ($option->sort_order ?: 0),
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            $optionToServiceId[(int) $option->id] = $serviceId;
        }

        if (empty($optionToServiceId)) {
            $this->seedDefaultServices($now);

            return;
        }

        $values = DB::table('attribute_values')
            ->where('attribute_id', $attribute->id)
            ->where('entity_type', 'leads')
            ->get(['entity_id', 'text_value', 'integer_value']);

        $pivotRows = [];

        foreach ($values as $value) {
            $raw = filled($value->text_value)
                ? $value->text_value
                : (string) $value->integer_value;

            $optionIds = array_values(array_filter(array_map('intval', explode(',', (string) $raw))));

            foreach ($optionIds as $optionId) {
                if (! isset($optionToServiceId[$optionId])) {
                    continue;
                }

                $pivotRows[$value->entity_id.':'.$optionToServiceId[$optionId]] = [
                    'lead_id'    => (int) $value->entity_id,
                    'service_id' => $optionToServiceId[$optionId],
                ];
            }
        }

        if (! empty($pivotRows)) {
            foreach (array_chunk(array_values($pivotRows), 500) as $chunk) {
                DB::table('lead_service')->insert($chunk);
            }
        }
    }

    /**
     * Seed default services when none exist.
     */
    protected function seedDefaultServices($now): void
    {
        $defaults = [
            'Website Development',
            'Social Media',
            'SEO',
            'Branding',
            'Paid Ads',
            'Email Marketing',
            'Content Marketing',
            'Other',
        ];

        foreach ($defaults as $index => $name) {
            DB::table('services')->insert([
                'name'       => $name,
                'sort_order' => $index + 1,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    /**
     * Remove legacy service_offered EAV attribute and related rows.
     */
    protected function removeServiceOfferedAttribute(): void
    {
        $attribute = DB::table('attributes')
            ->where('code', 'service_offered')
            ->where('entity_type', 'leads')
            ->first();

        if (! $attribute) {
            return;
        }

        DB::table('attribute_values')->where('attribute_id', $attribute->id)->delete();
        DB::table('attribute_options')->where('attribute_id', $attribute->id)->delete();
        DB::table('attributes')->where('id', $attribute->id)->delete();
    }
};

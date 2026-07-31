<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Webkul\Lead\Services\UsStateTimezoneService;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('persons', function (Blueprint $table) {
            $table->string('address_line')->nullable()->after('job_title');
            $table->string('city')->nullable()->after('address_line');
            $table->string('state', 64)->nullable()->index()->after('city');
            $table->string('country', 8)->nullable()->index()->after('state');
            $table->string('postcode', 32)->nullable()->after('country');
            $table->string('timezone', 64)->nullable()->index()->after('postcode');
        });

        $addressAttributeId = DB::table('attributes')
            ->where('code', 'address')
            ->where('entity_type', 'persons')
            ->value('id');

        if (! $addressAttributeId) {
            return;
        }

        $timezoneService = app(UsStateTimezoneService::class);

        DB::table('attribute_values')
            ->where('entity_type', 'persons')
            ->where('attribute_id', $addressAttributeId)
            ->whereNotNull('json_value')
            ->orderBy('id')
            ->chunkById(200, function ($rows) use ($timezoneService) {
                foreach ($rows as $row) {
                    $address = json_decode((string) $row->json_value, true);

                    if (! is_array($address)) {
                        continue;
                    }

                    $state = trim((string) ($address['state'] ?? '')) ?: null;
                    $country = trim((string) ($address['country'] ?? '')) ?: null;
                    $city = trim((string) ($address['city'] ?? '')) ?: null;
                    $postcode = trim((string) ($address['postcode'] ?? '')) ?: null;
                    $addressLine = trim((string) ($address['address'] ?? '')) ?: null;

                    if (! $state && ! $country && ! $city && ! $postcode && ! $addressLine) {
                        continue;
                    }

                    DB::table('persons')
                        ->where('id', $row->entity_id)
                        ->update([
                            'address_line' => $addressLine,
                            'city'         => $city,
                            'state'        => $state,
                            'country'      => $country,
                            'postcode'     => $postcode,
                            'timezone'     => $state ? $timezoneService->timezoneForState($state) : null,
                        ]);
                }
            });

        // Remove legacy EAV address values for persons; attribute definition stays for form UI.
        DB::table('attribute_values')
            ->where('entity_type', 'persons')
            ->where('attribute_id', $addressAttributeId)
            ->delete();
    }

    public function down(): void
    {
        $addressAttributeId = DB::table('attributes')
            ->where('code', 'address')
            ->where('entity_type', 'persons')
            ->value('id');

        if ($addressAttributeId) {
            $persons = DB::table('persons')
                ->where(function ($query) {
                    $query->whereNotNull('address_line')
                        ->orWhereNotNull('city')
                        ->orWhereNotNull('state')
                        ->orWhereNotNull('country')
                        ->orWhereNotNull('postcode');
                })
                ->get(['id', 'address_line', 'city', 'state', 'country', 'postcode']);

            foreach ($persons as $person) {
                $json = json_encode([
                    'address'  => $person->address_line,
                    'city'     => $person->city,
                    'state'    => $person->state,
                    'country'  => $person->country,
                    'postcode' => $person->postcode,
                ]);

                DB::table('attribute_values')->updateOrInsert(
                    [
                        'entity_type'  => 'persons',
                        'entity_id'    => $person->id,
                        'attribute_id' => $addressAttributeId,
                    ],
                    [
                        'json_value' => $json,
                    ]
                );
            }
        }

        Schema::table('persons', function (Blueprint $table) {
            $table->dropColumn([
                'address_line',
                'city',
                'state',
                'country',
                'postcode',
                'timezone',
            ]);
        });
    }
};

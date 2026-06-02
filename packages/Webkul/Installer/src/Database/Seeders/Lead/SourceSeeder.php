<?php

namespace Webkul\Installer\Database\Seeders\Lead;

use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class SourceSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * @param  array  $parameters
     * @return void
     */
    public function run($parameters = [])
    {
        $now = Carbon::now();

        $defaultLocale = $parameters['locale'] ?? config('app.locale');

        // Update existing sources with parent_id and sort_order
        $existingSources = DB::table('lead_sources')->whereNull('parent_id')->get();
        
        if ($existingSources->count() > 0) {
            // Update existing sources with sort_order
            DB::table('lead_sources')->where('id', 1)->update(['sort_order' => 1]);
            DB::table('lead_sources')->where('id', 2)->update(['sort_order' => 2]);
            DB::table('lead_sources')->where('id', 3)->update(['sort_order' => 3]);
            DB::table('lead_sources')->where('id', 4)->update(['sort_order' => 4]);
            DB::table('lead_sources')->where('id', 5)->update(['sort_order' => 5]);
            DB::table('lead_sources')->where('id', 6)->update(['sort_order' => 6]);
            DB::table('lead_sources')->where('id', 7)->update(['sort_order' => 7]);
            DB::table('lead_sources')->where('id', 8)->update(['sort_order' => 8]);
        } else {
            // Insert root sources if table is empty
            DB::table('lead_sources')->insert([
                [
                    'id'         => 1,
                    'parent_id'  => null,
                    'name'       => trans('installer::app.seeders.lead.source.email', [], $defaultLocale),
                    'sort_order' => 1,
                    'created_at' => $now,
                    'updated_at' => $now,
                ], [
                    'id'         => 2,
                    'parent_id'  => null,
                    'name'       => trans('installer::app.seeders.lead.source.web', [], $defaultLocale),
                    'sort_order' => 2,
                    'created_at' => $now,
                    'updated_at' => $now,
                ], [
                    'id'         => 3,
                    'parent_id'  => null,
                    'name'       => trans('installer::app.seeders.lead.source.web-form', [], $defaultLocale),
                    'sort_order' => 3,
                    'created_at' => $now,
                    'updated_at' => $now,
                ], [
                    'id'         => 4,
                    'parent_id'  => null,
                    'name'       => trans('installer::app.seeders.lead.source.phone', [], $defaultLocale),
                    'sort_order' => 4,
                    'created_at' => $now,
                    'updated_at' => $now,
                ], [
                    'id'         => 5,
                    'parent_id'  => null,
                    'name'       => trans('installer::app.seeders.lead.source.direct', [], $defaultLocale),
                    'sort_order' => 5,
                    'created_at' => $now,
                    'updated_at' => $now,
                ], [
                    'id'         => 6,
                    'parent_id'  => null,
                    'name'       => trans('installer::app.seeders.lead.source.upwork', [], $defaultLocale),
                    'sort_order' => 6,
                    'created_at' => $now,
                    'updated_at' => $now,
                ], [
                    'id'         => 7,
                    'parent_id'  => null,
                    'name'       => trans('installer::app.seeders.lead.source.fiverr', [], $defaultLocale),
                    'sort_order' => 7,
                    'created_at' => $now,
                    'updated_at' => $now,
                ], [
                    'id'         => 8,
                    'parent_id'  => null,
                    'name'       => trans('installer::app.seeders.lead.source.freelancer', [], $defaultLocale),
                    'sort_order' => 8,
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
            ]);
        }

        // Check if sub-sources already exist
        $subSourcesExist = DB::table('lead_sources')->whereNotNull('parent_id')->exists();
        
        if (!$subSourcesExist) {
            // Insert sub-sources (without parent_id, will link via pivot table)
            $invitationId = DB::table('lead_sources')->insertGetId([
                'parent_id'  => null,
                'name'       => trans('installer::app.seeders.lead.source.invitation', [], $defaultLocale),
                'sort_order' => 100,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            $bidId = DB::table('lead_sources')->insertGetId([
                'parent_id'  => null,
                'name'       => trans('installer::app.seeders.lead.source.bid', [], $defaultLocale),
                'sort_order' => 101,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            $directClientId = DB::table('lead_sources')->insertGetId([
                'parent_id'  => null,
                'name'       => trans('installer::app.seeders.lead.source.direct-client', [], $defaultLocale),
                'sort_order' => 102,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            // Link sub-sources to parent sources via pivot table
            $freelancePlatforms = [6, 7, 8]; // Upwork, Fiverr, Freelancer
            
            foreach ($freelancePlatforms as $parentId) {
                // Link Invitation to each platform
                DB::table('lead_source_parents')->insert([
                    'source_id' => $invitationId,
                    'parent_source_id' => $parentId,
                ]);
                
                // Link Bid to each platform
                DB::table('lead_source_parents')->insert([
                    'source_id' => $bidId,
                    'parent_source_id' => $parentId,
                ]);
                
                // Link Direct Client to each platform
                DB::table('lead_source_parents')->insert([
                    'source_id' => $directClientId,
                    'parent_source_id' => $parentId,
                ]);
            }
        }
    }
}

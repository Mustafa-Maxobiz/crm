<?php

namespace Webkul\Lead\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class BackfillLeadTitles extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'leads:backfill-titles
                            {--dry-run : Show how many rows would be updated without writing}
                            {--include-scraping : Also fill empty titles for Scraping-source leads}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Fill empty lead titles from company name for non-Scraping sources (main lead Title column)';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $scrapingSourceIds = DB::table('lead_sources')
            ->whereRaw('LOWER(name) = ?', ['scraping'])
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $query = DB::table('leads')
            ->join('organizations', 'organizations.id', '=', 'leads.organization_id')
            ->where(function ($query) {
                $query->whereNull('leads.title')
                    ->orWhere('leads.title', '');
            })
            ->whereNotNull('organizations.name')
            ->where('organizations.name', '!=', '');

        if (! $this->option('include-scraping') && ! empty($scrapingSourceIds)) {
            $query->where(function ($query) use ($scrapingSourceIds) {
                $query->whereNull('leads.lead_source_id')
                    ->orWhereNotIn('leads.lead_source_id', $scrapingSourceIds);
            });
        }

        $rows = (clone $query)->select('leads.id', 'organizations.name')->get();
        $count = $rows->count();

        if ($count === 0) {
            $this->info('No leads need a title backfill.');

            return self::SUCCESS;
        }

        if ($this->option('dry-run')) {
            $this->info("Would update {$count} lead(s).");

            return self::SUCCESS;
        }

        $updated = 0;

        foreach ($rows->chunk(500) as $chunk) {
            foreach ($chunk as $row) {
                $updated += DB::table('leads')
                    ->where('id', $row->id)
                    ->update(['title' => $row->name]);
            }
        }

        $this->info("Updated {$updated} lead(s) with company name as title.");

        return self::SUCCESS;
    }
}

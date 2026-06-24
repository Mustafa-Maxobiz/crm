<?php

namespace Webkul\MetaLead\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Webkul\MetaLead\Services\MetaLeadProcessorService;

class ProcessMetaLeadWebhookJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(
        public string $leadgenId,
        public ?int $pageId = null,
        public ?array $webhookValue = null,
    ) {}

    public function handle(MetaLeadProcessorService $processor): void
    {
        try {
            $processor->process($this->leadgenId, $this->pageId, $this->webhookValue);
        } catch (\Throwable $exception) {
            Log::error('Meta Lead Ads: job processing failed.', [
                'leadgen_id' => $this->leadgenId,
                'message'    => $exception->getMessage(),
            ]);
        }
    }
}

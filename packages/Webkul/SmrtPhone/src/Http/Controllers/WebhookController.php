<?php

namespace Webkul\SmrtPhone\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Log;
use Webkul\SmrtPhone\Services\CallLogProcessorService;

class WebhookController extends Controller
{
    public function __construct(
        protected CallLogProcessorService $processor,
    ) {}

    public function handle(Request $request): JsonResponse
    {
        if (! $this->verifySecret($request)) {
            Log::warning('SmrtPhone webhook rejected: invalid secret.');

            return response()->json(['message' => 'Invalid secret'], 403);
        }

        $payload = $request->all();

        if (empty($payload)) {
            return response()->json(['message' => 'Empty payload'], 422);
        }

        /**
         * smrtPhone may send a single event object or a list of events.
         */
        $events = array_is_list($payload) ? $payload : [$payload];

        $processed = 0;

        foreach ($events as $eventPayload) {
            if (! is_array($eventPayload)) {
                continue;
            }

            try {
                $log = $this->processor->process($eventPayload);

                if ($log) {
                    $processed++;
                }
            } catch (\Throwable $exception) {
                Log::error('SmrtPhone webhook processing failed.', [
                    'message' => $exception->getMessage(),
                    'payload' => $eventPayload,
                ]);
            }
        }

        return response()->json([
            'message'   => 'Webhook received',
            'processed' => $processed,
        ]);
    }

    protected function verifySecret(Request $request): bool
    {
        $configured = (string) config('smrtphone.webhook_secret');

        if ($configured === '') {
            return true;
        }

        $provided = (string) (
            $request->header('X-SmrtPhone-Secret')
            ?? $request->header('X-Webhook-Secret')
            ?? $request->query('secret')
            ?? ''
        );

        return hash_equals($configured, $provided);
    }
}

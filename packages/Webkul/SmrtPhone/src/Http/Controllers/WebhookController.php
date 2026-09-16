<?php

namespace Webkul\SmrtPhone\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Log;
use Webkul\SmrtPhone\Services\CallLogProcessorService;
use Webkul\SmrtPhone\Services\SmrtPhoneConfigService;

class WebhookController extends Controller
{
    public function __construct(
        protected CallLogProcessorService $processor,
        protected SmrtPhoneConfigService $configService,
    ) {}

    public function handle(Request $request): JsonResponse
    {
        if (! $this->verifySecret($request)) {
            Log::warning('SmrtPhone webhook rejected: invalid secret.');

            return response()->json(['message' => 'Invalid secret'], 403);
        }

        $payload = $this->webhookPayload($request);

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

            $eventPayload = $this->sanitizePayload($eventPayload);

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
        $configured = $this->configService->webhookSecret();

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

    protected function webhookPayload(Request $request): array
    {
        if ($request->isJson()) {
            return $request->json()->all();
        }

        return $request->request->all();
    }

    protected function sanitizePayload(array $payload): array
    {
        foreach ([
            'secret',
            'webhook_secret',
            'token',
            'api_key',
        ] as $key) {
            unset($payload[$key]);
        }

        return $payload;
    }
}

<?php

namespace Webkul\MetaLead\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Log;
use Webkul\MetaLead\Jobs\ProcessMetaLeadWebhookJob;

class WebhookController extends Controller
{
    public function handle(Request $request): Response|string
    {
        if ($request->isMethod('get')) {
            return $this->verify($request);
        }

        if (! $this->verifySignature($request)) {
            Log::warning('Meta Lead Ads: invalid webhook signature.');

            return response('Invalid signature', 403);
        }

        $payload = $request->all();

        foreach ($payload['entry'] ?? [] as $entry) {
            foreach ($entry['changes'] ?? [] as $change) {
                if (($change['field'] ?? '') !== 'leadgen') {
                    continue;
                }

                $leadgenId = $change['value']['leadgen_id'] ?? null;
                $pageId = $change['value']['page_id'] ?? null;

                if ($leadgenId) {
                    try {
                        ProcessMetaLeadWebhookJob::dispatch(
                            $leadgenId,
                            isset($pageId) ? (int) $pageId : null,
                            $change['value'] ?? null,
                        );
                    } catch (\Throwable $exception) {
                        Log::error('Meta Lead Ads: failed to queue lead processing.', [
                            'leadgen_id' => $leadgenId,
                            'message'    => $exception->getMessage(),
                        ]);
                    }
                }
            }
        }

        return response('EVENT_RECEIVED', 200);
    }

    protected function verify(Request $request): Response|string
    {
        $mode = $request->query('hub_mode') ?? $request->query('hub.mode');
        $token = $request->query('hub_verify_token') ?? $request->query('hub.verify_token');
        $challenge = $request->query('hub_challenge') ?? $request->query('hub.challenge');

        if ($mode === 'subscribe' && hash_equals((string) config('meta_lead.verify_token'), (string) $token)) {
            return response($challenge, 200)->header('Content-Type', 'text/plain');
        }

        Log::warning('Meta Lead Ads: webhook verification failed.', [
            'mode'          => $mode,
            'token_matches' => hash_equals((string) config('meta_lead.verify_token'), (string) $token),
        ]);

        return response('Forbidden', 403);
    }

    protected function verifySignature(Request $request): bool
    {
        $secret = config('meta_lead.app_secret');

        if (! $secret) {
            return app()->environment('local');
        }

        $signature = $request->header('X-Hub-Signature-256');

        if (! $signature) {
            return false;
        }

        $expected = 'sha256='.hash_hmac('sha256', $request->getContent(), $secret);

        return hash_equals($expected, $signature);
    }
}

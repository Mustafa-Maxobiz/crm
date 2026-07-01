<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Queue;
use Webkul\MetaLead\Http\Controllers\WebhookController;
use Webkul\MetaLead\Jobs\ProcessMetaLeadWebhookJob;

beforeEach(function () {
    Queue::fake();

    $this->controller = new WebhookController;
});

it('verifies meta webhook subscription challenge', function () {
    config(['meta_lead.verify_token' => 'test-verify-token']);

    $request = Request::create('/webhook', 'GET', [
        'hub_mode'         => 'subscribe',
        'hub_verify_token' => 'test-verify-token',
        'hub_challenge'    => 'challenge-123',
    ]);

    $response = $this->controller->handle($request);

    expect($response->getContent())->toBe('challenge-123')
        ->and($response->getStatusCode())->toBe(200);
});

it('rejects invalid meta webhook verification token', function () {
    config(['meta_lead.verify_token' => 'expected-token']);

    $request = Request::create('/webhook', 'GET', [
        'hub_mode'         => 'subscribe',
        'hub_verify_token' => 'wrong-token',
        'hub_challenge'    => 'challenge-123',
    ]);

    $response = $this->controller->handle($request);

    expect($response->getStatusCode())->toBe(403);
});

it('accepts signed meta webhook payloads', function () {
    config(['meta_lead.app_secret' => 'super-secret']);

    $payload = json_encode([
        'entry' => [
            [
                'changes' => [
                    [
                        'field' => 'leadgen',
                        'value' => [
                            'leadgen_id' => '123456789',
                            'page_id'    => 42,
                        ],
                    ],
                ],
            ],
        ],
    ]);

    $signature = 'sha256='.hash_hmac('sha256', $payload, 'super-secret');

    $request = Request::create('/webhook', 'POST', [], [], [], [
        'HTTP_X-Hub-Signature-256' => $signature,
        'CONTENT_TYPE'             => 'application/json',
    ], $payload);

    $response = $this->controller->handle($request);

    expect($response->getContent())->toBe('EVENT_RECEIVED')
        ->and($response->getStatusCode())->toBe(200);

    Queue::assertPushed(ProcessMetaLeadWebhookJob::class);
});

it('rejects unsigned meta webhook payloads when secret is configured', function () {
    config(['meta_lead.app_secret' => 'super-secret']);

    $request = Request::create('/webhook', 'POST', [
        'entry' => [],
    ]);

    $response = $this->controller->handle($request);

    expect($response->getStatusCode())->toBe(403);
});

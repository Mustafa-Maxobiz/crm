<?php

namespace Webkul\MetaLead\Services;

use GuzzleHttp\Client;
use Illuminate\Support\Facades\Log;

class MetaGraphApiService
{
    public function __construct(protected Client $client) {}

    public function fetchLeadData(string $leadgenId): ?array
    {
        $token = config('meta_lead.page_access_token');

        if (! $token) {
            Log::error('Meta Lead Ads: page access token is not configured.');

            return null;
        }

        $version = config('meta_lead.graph_api_version', 'v21.0');

        try {
            $response = $this->client->get("https://graph.facebook.com/{$version}/{$leadgenId}", [
                'query' => [
                    'access_token' => $token,
                    'fields'       => 'id,created_time,field_data,ad_id,form_id,campaign_id',
                ],
            ]);

            $data = json_decode($response->getBody()->getContents(), true);

            if (! is_array($data)) {
                return null;
            }

            if (! empty($data['form_id'])) {
                $data['form_name'] = $this->fetchResourceName($data['form_id'], $token, $version);
            }

            if (! empty($data['campaign_id'])) {
                $data['campaign_name'] = $this->fetchResourceName($data['campaign_id'], $token, $version);
            }

            return $data;
        } catch (\Throwable $exception) {
            Log::error('Meta Lead Ads: failed to fetch lead from Graph API.', [
                'leadgen_id' => $leadgenId,
                'message'    => $exception->getMessage(),
            ]);

            return null;
        }
    }

    protected function fetchResourceName(string $resourceId, string $token, string $version): ?string
    {
        try {
            $response = $this->client->get("https://graph.facebook.com/{$version}/{$resourceId}", [
                'query' => [
                    'access_token' => $token,
                    'fields'       => 'name',
                ],
            ]);

            $data = json_decode($response->getBody()->getContents(), true);

            return $data['name'] ?? null;
        } catch (\Throwable) {
            return null;
        }
    }

    public function parseFieldData(array $fieldData): array
    {
        $parsed = [
            'full_name' => null,
            'email'     => null,
            'phone'     => null,
        ];

        foreach ($fieldData as $field) {
            $name = strtolower($field['name'] ?? '');
            $value = $field['values'][0] ?? null;

            if (! $value) {
                continue;
            }

            if (in_array($name, ['full_name', 'name', 'first_name'])) {
                $parsed['full_name'] = $parsed['full_name']
                    ? trim($parsed['full_name'].' '.$value)
                    : $value;
            } elseif (in_array($name, ['last_name']) && $parsed['full_name']) {
                $parsed['full_name'] = trim($parsed['full_name'].' '.$value);
            } elseif (in_array($name, ['email', 'email_address'])) {
                $parsed['email'] = $value;
            } elseif (in_array($name, ['phone_number', 'phone', 'mobile', 'contact_number'])) {
                $parsed['phone'] = $value;
            }
        }

        return $parsed;
    }
}

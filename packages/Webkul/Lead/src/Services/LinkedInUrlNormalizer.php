<?php

namespace Webkul\Lead\Services;

class LinkedInUrlNormalizer
{
    public static function normalize(string $url): string
    {
        $url = trim($url);

        if (! preg_match('/^https?:\/\//i', $url)) {
            $url = 'https://'.$url;
        }

        $parts = parse_url($url);

        if (! $parts || empty($parts['host'])) {
            return $url;
        }

        $host = preg_replace('/^www\./i', '', strtolower($parts['host']));
        $path = strtolower($parts['path'] ?? '');
        $path = preg_replace('#/+#', '/', $path);
        $path = rtrim($path, '/');

        return 'https://'.$host.$path;
    }

    public static function normalizeForCompare(string $url): string
    {
        $normalized = strtolower(trim($url));
        $normalized = preg_replace('/^https?:\/\//', '', $normalized);
        $normalized = preg_replace('/^www\./', '', $normalized);

        return rtrim($normalized, '/');
    }

    public static function sqlCompareExpression(string $column): string
    {
        return "TRIM(TRAILING '/' FROM REPLACE(REPLACE(REPLACE(REPLACE(LOWER({$column}), 'https://www.', ''), 'http://www.', ''), 'https://', ''), 'http://', ''))";
    }
}

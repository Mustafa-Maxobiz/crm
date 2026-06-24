<?php

return [
  'app_id'              => env('META_APP_ID'),
  'app_secret'          => env('META_APP_SECRET'),
  'verify_token'        => env('META_VERIFY_TOKEN'),
  'page_access_token'   => env('META_PAGE_ACCESS_TOKEN'),
  'page_id'             => env('META_PAGE_ID'),
  'graph_api_version'   => env('META_GRAPH_API_VERSION', 'v21.0'),
  'default_user_id'     => env('META_DEFAULT_USER_ID'),
  'default_assigned_user_ids' => array_values(array_filter(array_map(
      'intval',
      array_map('trim', explode(',', (string) env('META_DEFAULT_ASSIGNED_USER_IDS', '')))
  ))),
  'notification_emails' => env('META_NOTIFICATION_EMAILS'),
  'lead_source_name'    => 'Meta Ads',
];

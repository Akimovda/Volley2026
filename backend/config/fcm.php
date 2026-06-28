<?php

return [
    'project_id'           => env('FCM_PROJECT_ID', ''),
    'service_account_path' => env('FCM_SERVICE_ACCOUNT_PATH')
        ? (str_starts_with(env('FCM_SERVICE_ACCOUNT_PATH'), '/')
            ? env('FCM_SERVICE_ACCOUNT_PATH')
            : base_path(env('FCM_SERVICE_ACCOUNT_PATH')))
        : storage_path('app/fcm/service-account.json'),
];

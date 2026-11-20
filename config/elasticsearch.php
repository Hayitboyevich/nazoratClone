<?php

return [
    'host' => env('ELASTICSEARCH_HOST', 'http://localhost:9200'),
    'api_key' => env('ELASTICSEARCH_API_KEY', ''),
    'ssl_verification' => env('ELASTIC_VERIFY_SSL', false),
    'prefix' => env('ELASTICSEARCH_PREFIX', ''),
];

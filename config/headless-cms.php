<?php

declare(strict_types=1);

return [
    'enabled' => true,

    /*
     * Base path all headless routes are mounted under.
     */
    'prefix' => getenv('HEADLESS_API_PREFIX') ?: '/api/headless',

    /*
     * Public read allows GET requests without an API key. Any write
     * operation always requires a valid key carrying the right scope.
     */
    'auth' => [
        'required' => true,
        'header' => 'X-API-Key',
    ],

    'pagination' => [
        'default_size' => 20,
        'max_size' => 100,
    ],

    'table_prefix' => getenv('HEADLESS_API_TABLE_PREFIX')
        ?: (getenv('PW_TABLE_PREFIX') ?: 'pw_'),
];
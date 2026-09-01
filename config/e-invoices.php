<?php

return [

    'transmission' => [
        'mode' => env('E_INVOICES_TX_MODE', 'stub'),
        'endpoint' => env('E_INVOICES_AP_ENDPOINT'),
        'api_key' => env('E_INVOICES_AP_KEY'),
    ],

    'validation' => [
        // Path to the UBL 2.1 Invoice XSD (defaults to the package's bundled copy).
        'schema' => null,
    ],

    'signature' => [
        'certificate' => env('E_INVOICES_CERT_PATH'),
        'private_key' => env('E_INVOICES_KEY_PATH'),
        'key_password' => env('E_INVOICES_KEY_PASSWORD'),
    ],

];

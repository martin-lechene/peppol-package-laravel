<?php

return [

    'transmission' => [
        'mode' => env('E_INVOICES_TX_MODE', 'stub'),
        'endpoint' => env('E_INVOICES_AP_ENDPOINT'),
        'api_key' => env('E_INVOICES_AP_KEY'),
    ],

];

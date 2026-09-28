<?php

return [

    /*
    | The public website address and its APP_KEY. The offer link is signed
    | with that key, which is what the public site checks.
    |
    */

    'public_base_url' => rtrim((string) env('PUBLIC_TICKETING_URL', 'https://edandtheshadowboys.com'), '/'),

    'campaign_key' => env('PUBLIC_TICKETING_APP_KEY'),

];

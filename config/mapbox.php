<?php

return [
    'access_token' => env('MAP_BOX_PUBLIC_TOKEN', ''),
    'style' => env('MAPBOX_STYLE', 'mapbox/streets-v12'),
    'default_zoom' => env('MAPBOX_DEFAULT_ZOOM', 12),

    'endpoints' => [
        'geocode_forward' => 'https://api.mapbox.com/search/geocode/v6/forward',
    ],
];

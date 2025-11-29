<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');

/**
 * Reverse geocode latitude and longitude to get location details
 * Uses OpenStreetMap Nominatim API (Free)
 */
function reverse_geocode($lat, $lon) {
    $url = "https://nominatim.openstreetmap.org/reverse?lat={$lat}&lon={$lon}&format=json";

    $options = [
        'http' => [
            'header' => "User-Agent: LavalustApp/1.0\r\n" // Nominatim requires a User-Agent
        ]
    ];

    $context = stream_context_create($options);
    $response = file_get_contents($url, false, $context);

    if (!$response) return null;

    return json_decode($response, true);
}

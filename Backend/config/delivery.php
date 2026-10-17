<?php

$boundary = json_decode((string) env('SANAA_DELIVERY_POLYGON', ''), true);

return [
    // Accepts an owner-approved GeoJSON Polygon or [[longitude, latitude], ...].
    'sanaa_polygon' => is_array($boundary) && ($boundary['type'] ?? null) === 'Polygon'
        ? $boundary['coordinates'][0] ?? null
        : $boundary,
];

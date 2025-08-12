<?php
#ddev-generated

// Create a stanza for $PLATFORM_ROUTES

// Args:
// route (like https://{default}/)
// id
// production_url
// upstream
// type
// original_url

$route = $argv[1] ?? '';
$id = $argv[2] ?? '';
$production_url = $argv[3] ?? '';
$upstream = $argv[4] ?? '';
$type = $argv[5] ?? '';
$original_url = $argv[6] ?? '';

// Handle optional id field
$id_value = empty($id) ? null : $id;

// Create route structure
$route_stanza = [
    $route => [
        'primary' => true,
        'id' => $id_value,
        'production_url' => $production_url,
        'attributes' => new stdClass(), // Empty object
        'upstream' => $upstream,
        'type' => $type,
        'original_url' => $original_url
    ]
];

// Output JSON (just the route content, not wrapped in outer braces)
echo json_encode($route_stanza[$route], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
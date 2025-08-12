<?php
#ddev-generated

$relationshipname = $argv[1] ?? '';

// Create Redis persistent relationship structure (identical to redis)
$redis_stanza = [
    $relationshipname => [
        [
            'username' => null,
            'scheme' => 'redis',
            'service' => 'cache',
            'fragment' => null,
            'ip' => '255.255.255.255',
            'hostname' => 'redis',
            'public' => false,
            'cluster' => 'ddev-dummy-cluster',
            'host' => 'redis',
            'rel' => 'redis',
            'query' => new stdClass(), // Empty object
            'path' => null,
            'password' => null,
            'type' => 'redis:6.0',
            'port' => 6379,
            'host_mapped' => false
        ]
    ]
];

// Output JSON
echo json_encode($redis_stanza[$relationshipname], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
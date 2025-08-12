<?php
#ddev-generated

$relationshipname = $argv[1] ?? '';

// Create Memcached relationship structure
$memcached_stanza = [
    $relationshipname => [
        [
            'service' => 'memcached',
            'ip' => '255.255.255.255',
            'hostname' => 'memcached',
            'cluster' => 'ddev-dummy-cluster',
            'host' => 'memcached',
            'rel' => 'memcached',
            'scheme' => 'memcached',
            'type' => 'memcached:1.6',
            'port' => 11211
        ]
    ]
];

// Output JSON
echo json_encode($memcached_stanza[$relationshipname], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
<?php
#ddev-generated

$relationshipname = $argv[1] ?? '';

// Create Elasticsearch relationship structure
$elasticsearch_stanza = [
    $relationshipname => [
        [
            'username' => null,
            'scheme' => 'http',
            'service' => 'search',
            'fragment' => null,
            'ip' => '255.255.255.255',
            'hostname' => 'elasticsearch',
            'public' => false,
            'cluster' => 'ddev-dummy-cluster',
            'host' => 'elasticsearch',
            'rel' => 'elasticsearch',
            'query' => new stdClass(), // Empty object
            'path' => null,
            'password' => null,
            'type' => 'elasticsearch:7.5',
            'port' => 9200,
            'host_mapped' => false
        ]
    ]
];

// Output JSON
echo json_encode($elasticsearch_stanza[$relationshipname], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
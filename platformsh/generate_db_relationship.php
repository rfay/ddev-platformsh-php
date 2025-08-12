<?php
#ddev-generated

// Args:
// service name, like 'dbmysql' or 'db'
// db type, like mariadb:10.4 or postgres:14
// relationshipname, like "database" (but it's arbitrary, used in PLATFORM_RELATIONSHIPS)

$dbservice = $argv[1] ?? '';
$dbtype = $argv[2] ?? '';
$relationshipname = $argv[3] ?? '';

// Determine database scheme and port based on type
$dbscheme = '';
$dbport = 0;
$rel = '';

if (str_starts_with($dbtype, 'mariadb') || str_starts_with($dbtype, 'mysql')) {
    $dbscheme = 'mysql';
    $dbport = 3306;
    $rel = 'mysql';
} elseif (str_starts_with($dbtype, 'postgres')) {
    $dbscheme = 'pgsql';
    $dbport = 5432;
    $rel = 'pgsql';
} else {
    fwrite(STDERR, "no recognized dbtype: '$dbtype'\n");
    exit(1);
}

// Create database relationship structure
$db_stanza = [
    $relationshipname => [
        [
            'username' => 'db',
            'scheme' => $dbscheme,
            'service' => $dbservice,
            'fragment' => null,
            'ip' => '255.255.255.255',
            'hostname' => 'db',
            'public' => false,
            'cluster' => 'ddev-dummy-cluster',
            'host' => 'db',
            'rel' => $rel,
            'query' => [
                'is_master' => true
            ],
            'path' => 'db',
            'password' => 'db',
            'type' => $dbtype,
            'port' => $dbport,
            'host_mapped' => false
        ]
    ]
];

// Output JSON (without the outer braces, just the relationship content)
echo json_encode($db_stanza[$relationshipname], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
<?php
// Default settings = a fresh XAMPP install (user "root", no password).
// Need different values on your own PC?  Copy config.local.example.php to
// config.local.php and edit it. That file is git-ignored, so it never
// overwrites your groupmates' settings.
$config = [
    'db_host' => '127.0.0.1',
    'db_port' => 3306,
    'db_name' => 'cleardrop_db',
    'db_user' => 'root',
    'db_pass' => '',
    'station_name' => 'CLEARDROP WATER STATION',
    'station_addr' => 'Legazpi City, Albay',
];

if (file_exists(__DIR__ . '/config.local.php')) {
    $config = array_merge($config, require __DIR__ . '/config.local.php');
}
return $config;

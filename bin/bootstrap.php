<?php

$config = parse_ini_file(__DIR__ . '/../config/config.ini');

$database = $config['database'];
$pdo = new \PDO(sprintf('mysql:host=%s;dbname=%s', $database['host'], $database['name']), $database['user'], $database['pass']);
$pdo->setAttribute( PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION );

return [$config, $pdo];

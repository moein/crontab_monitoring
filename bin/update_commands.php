<?php

/** @var PDO $pdo */
list($config, $pdo) = include(__DIR__.'/bootstrap.php');

function get_commands($file, $ignoreStrings, $users) {
    if (!file_exists($file)) {
        echo sprintf('Invalid "%s" provided! Ignoring this one.'.PHP_EOL, $file);
        return [];
    }
    $content = file_get_contents($file);
    $commands = [];
    foreach (explode("\n", $content) as $line) {
        if (!$line || ($line[0] != '*' && !is_numeric($line[0]))) {
            continue;
        }
        $lineParts = preg_split('/\s+/', $line);

        $lineParts = array_slice($lineParts, 5);
        if (in_array($lineParts[0], $users)) {
            unset($lineParts[0]);
        }

        $command = trim(implode(' ', $lineParts));

        foreach ($ignoreStrings as $ignoreString) {
            if (strpos($command, $ignoreString) !== false) {
                continue(2);
            }
        }

        $commands[] = $command;
    }

    return $commands;
}

$values = [];
foreach ($config['crontab_files'] as $crontabFile) {
    foreach (get_commands($crontabFile, $config['ignore_strings'], $config['crontab_users']) as $command) {
        $values[] = sprintf('("%s", "%s")', $command, md5($command));
    }
}

if (!count($values)) {
    echo 'No command was found'.PHP_EOL;
    exit(1);
}

$sth = $pdo->prepare('INSERT IGNORE INTO command (command, hash) VALUES '.implode(',', $values));
$sth->execute();

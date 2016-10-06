<?php

$config = parse_ini_file(__DIR__.'/config.ini');

function get_commands($file, $ignoreStrings, $users) {
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

function get_command_hash($command, $pid) {
    return md5($pid.'__'.$command);
}

function monitor($config, \PDO $pdo) {
    $currentTime = date('Y-m-d H:i:s');
    $runningCommandsHashes = [0];
    foreach ($config['crontab_files'] as $crontabFile) {
        $commands = get_commands($crontabFile, $config['ignore_strings'], $config['crontab_users']);
        $output = shell_exec(sprintf('ps aux | grep "%s"', $config['crontab_runner']));
        foreach (explode("\n", $output) as $process) {
            $processParts = explode($config['crontab_runner'].' ', $process);
            if (count($processParts) != 2) {
                continue;
            }
            $processCommand = trim($processParts[1]);
            if (!in_array($processCommand, $commands)) {
                continue;
            }

            $processParts = preg_split('/\s+/', $process);
            $pid = $processParts[1];

            $hash = get_command_hash($processCommand, $pid);
            $runningCommandsHashes[] = $hash;

            $sth = $pdo->prepare('SELECT id FROM crontab WHERE hash = :hash AND finished_at IS NULL');
            $sth->execute([':hash' => $hash]);
            $result = $sth->fetchColumn();
            if (!$result) {
                $insert = $pdo->prepare('INSERT INTO crontab(command, pid, started_at, hash) VALUES (:command, :pid, :started_at, :hash)');
                $insert->execute([
                    ':command' => $processCommand,
                    ':pid' => $pid,
                    ':started_at' => $currentTime,
                    ':hash' => $hash
                ]);
            }
        }
    }



    $sth = $pdo->prepare(sprintf(
        'UPDATE crontab SET finished_at = :time WHERE hash NOT IN (%s) AND finished_at IS NULL',
        implode(',', array_map(function($hash) {return "'$hash'";}, $runningCommandsHashes))
    ));
    $sth->execute([':time' => $currentTime]);

}

$database = $config['database'];
$pdo = new \PDO(sprintf('mysql:host=%s;dbname=%s', $database['host'], $database['name']), $database['user'], $database['pass']);
$pdo->setAttribute( PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION );

if ($config['check_interval'] > 0) {
    while(true) {
        monitor($config, $pdo);
        sleep($config['check_interval']);
    }
} else {
    monitor($config, $pdo);
}


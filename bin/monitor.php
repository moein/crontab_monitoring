<?php

/** @var PDO $pdo */
list($config, $pdo) = include(__DIR__.'/bootstrap.php');

function get_commands(PDO $pdo) {
    $sth = $pdo->prepare('SELECT id, command FROM command');
    $sth->execute();

    $commands = [];
    foreach ($sth->fetchAll() as $command) {
        $commands[$command['id']] = $command['command'];
    }

    return $commands;
}

function get_command_hash($command, $pid) {
    return md5($pid.'__'.$command);
}

function monitor($config, \PDO $pdo) {
    $currentTime = date('Y-m-d H:i:s');
    $runningCommandsHashes = [0];

    $commands = get_commands($pdo);
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

        $commandId = array_search($processCommand, $commands);

        $processParts = preg_split('/\s+/', $process);
        $pid = $processParts[1];

        $hash = get_command_hash($commandId, $pid);
        $runningCommandsHashes[] = $hash;

        $sth = $pdo->prepare('SELECT id FROM command_stats WHERE hash = :hash AND finished_at IS NULL');
        $sth->execute([':hash' => $hash]);
        $result = $sth->fetchColumn();
        if (!$result) {
            $insert = $pdo->prepare('INSERT INTO command_stats(command_id, pid, started_at, hash) VALUES (:command_id, :pid, :started_at, :hash)');
            $insert->execute([
                ':command_id' => $commandId,
                ':pid' => $pid,
                ':started_at' => $currentTime,
                ':hash' => $hash
            ]);
        }
    }

    $sth = $pdo->prepare(sprintf(
        'UPDATE command_stats SET finished_at = :time WHERE hash NOT IN (%s) AND finished_at IS NULL',
        implode(',', array_map(function($hash) {return "'$hash'";}, $runningCommandsHashes))
    ));
    $sth->execute([':time' => $currentTime]);

}



if ($config['check_interval'] > 0) {
    while(true) {
        monitor($config, $pdo);
        sleep($config['check_interval']);
    }
} else {
    monitor($config, $pdo);
}


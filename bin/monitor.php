<?php

/** @var PDO $pdo */
list($config, $pdo) = include(__DIR__.'/bootstrap.php');

function get_commands(PDO $pdo, $server) {
    $sth = $pdo->prepare('SELECT id, command, user FROM command WHERE server = :server');
    $sth->execute([':server' => $server]);

    $commands = [];
    foreach ($sth->fetchAll() as $command) {
        $commands[$command['user']][$command['id']] = $command['command'];
    }

    return $commands;
}

function get_command_hash($command, $pid) {
    return md5($pid.'__'.$command);
}

function monitor($config, \PDO $pdo) {
    $currentTime = date('Y-m-d H:i:s');
    $runningCommandsHashes = [0];

    foreach ($config['servers'] as $server) {
        $serverParts = explode(':', $server);
        $serverSsh = $serverParts[0];
        $users = explode(',', $serverParts[1]);

        $listProcessesCommand = $config['list_processes_command'];

        $commands = get_commands($pdo, $serverSsh);
        $output = shell_exec(sprintf('%s %s \'%s | grep "%s"\'',$config['ssh_path'], $serverSsh, $listProcessesCommand, $config['crontab_runner']));
        foreach (explode("\n", $output) as $process) {
            $processParts = explode($config['crontab_runner'].' ', $process);
            if (count($processParts) != 2) {
                continue;
            }
            $processCommand = trim($processParts[1]);

            $processParts = preg_split('/\s+/', $process);
            $user = $processParts[0];
            $pid = $processParts[1];


            if (!in_array($user, $users) || !in_array($processCommand, $commands[$user])) {
                continue;
            }

            $commandId = array_search($processCommand, $commands[$user]);


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
            'UPDATE command_stats SET finished_at = :time, duration = timestampdiff(SECOND,started_at,finished_at) WHERE hash NOT IN (%s) AND finished_at IS NULL',
            implode(',', array_map(function($hash) {return "'$hash'";}, $runningCommandsHashes))
        ));
        $sth->execute([':time' => $currentTime]);

        $sth = $pdo->prepare('UPDATE command_stats SET duration = timestampdiff(SECOND,started_at,NOW()) WHERE finished_at IS NULL');
        $sth->execute();

        $sth = $pdo->prepare('UPDATE command c JOIN command_stats cs ON cs.command_id = c.id AND finished_at IS NULL SET c.last_pid = cs.pid, c.last_duration = cs.duration');
        $sth->execute();
    }
}



if ($config['check_interval'] > 0) {
    while(true) {
        monitor($config, $pdo);
        sleep($config['check_interval']);
    }
} else {
    monitor($config, $pdo);
}


<?php

/** @var PDO $pdo */
list($config, $pdo) = include(__DIR__.'/bootstrap.php');

function get_commands($serverSsh, $users, $sshPath, $ignoreStrings) {
    $commands = [];

    foreach ($users as $user){
        if ($user[0] === '/') {
            $content = shell_exec(sprintf('%s %s "cat %s"', $sshPath, $serverSsh, $user));
        } else {
            $content = shell_exec(sprintf('%s %s "crontab -u %s -l"', $sshPath, $serverSsh, $user));
        }

        foreach (explode("\n", $content) as $line) {
            $commandUser = $user;
            if (!$line || ($line[0] != '*' && !is_numeric($line[0]))) {
                continue;
            }
            $lineParts = preg_split('/\s+/', $line);

            $lineParts = array_slice($lineParts, 5);
            if ($user[0] === '/') {
                $commandUser = $lineParts[0];
                unset($lineParts[0]);
            } elseif ($lineParts[0] == $user) {
                unset($lineParts[0]);
            }


            $command = trim(implode(' ', $lineParts));

            foreach ($ignoreStrings as $ignoreString) {
                if (strpos($command, $ignoreString) !== false) {
                    continue(2);
                }
            }

            $commands[] = ['command' => $command, 'user' => $commandUser];
        }
    }

    return $commands;
}

$values = [];
$sthParameters = [];
foreach ($config['servers'] as $server) {
    $serverParts = explode(':', $server);
    $serverSsh = $serverParts[0];
    $users = explode(',', $serverParts[1]);

    foreach (get_commands($serverSsh, $users, $config['ssh_path'], $config['ignore_strings']) as $command) {
        $user = $command['user'];
        $command = $command['command'];
        $sthParameters[':command'.count($values)] = $command;
        $values[] = sprintf('(:command%d, "%s", "%s", "%s")', count($values), $serverSsh, $user, md5($command.'__'.$serverSsh.'__'.$user));
    }
}

if (!count($values)) {
    echo 'No command was found'.PHP_EOL;
    exit(1);
}

$sth = $pdo->prepare('INSERT IGNORE INTO command (command, server, user, hash) VALUES '.implode(',', $values));
$sth->execute($sthParameters);

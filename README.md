# Crontab monitoring

## What is it?
The idea of this project is a simple script that can sit in a machine and monitor the amount of time that 
each command in crontab takes with minimum effort.

## How do I use it?
It's pretty simple. 
 - Download the project.
 - Copy config.ini.dist to config.ini
 - Update the config.ini file
 - Run update_commands.php to save the commands
 - Run monitor.php to track the execution time
## Explain the config names!

### check_interval
If this value is 0 then the script checks the crontab commands once and goes out. On the other hand if it's a number greater than 0 it will use that interval and checks every `check_interval` seconds the crontab commands.

The 0 value is mostly used when you put this script as a cronjob for example to run every 30 seconds.
When not 0 it's better to be used as a service so in case it dies the system takes care of launching it agian.

Take in account based on how often you run the script or the value of check_interval the timing can be less or more precise.
This script is mainly for commands that take more than 10 seconds and not short commands.

### ssh_path
The path to ssh binary file. You can find it using which ssh

### crontab_runner
This is the command used by crontab to run a cronjob. Generally the default value should work fine but to make sure
that this is the right value put a cronjob with sleep 10 and once it runs run ps aux | grep sleep

You will see 2 instances of sleep, one with a command behind it and one alone. The first one tells you what exactly to use.
In debian or macos case it shows `/bin/sh -c sleep 10`

### ignore_strings
A list of strings that if a cronjob contains it will be ignored. By default only monitor.php is added to avoid monitoring the script itself.

### servers
The monitoring system checks the crontabs in multiple servers. The values of this field should be the following way.
user@server_ip:user1,file1,user2,file2

If a user is passed crontab -u user -l is used and if a file path is passed the cat file is used.

### database
This is where the stats and commands are kept.

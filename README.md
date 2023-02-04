# MySQL-Database-Mail-Backups

# Download PHPMailer 
From https://github.com/PHPMailer/PHPMailer


#Add the following line to Cron Jobs:
0 0 * * * php /home/your_account/mydbbackup/index.php >/dev/null 2>&1
Numbers and asterisks are the interval part, see the cheat sheet below.
php /home/your_account/backup2mail/index.php means that PHP will execute the script, and >/dev/null 2>&1 tells Cron not to send output to e-mail specified in the first line of Cron configuration file.

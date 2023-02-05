# MySQL-Database-Mail-Backups
Backingup your web site’s database is considered a common sense. We all know that, yet we often forget about it. This mini PHP application that creates regular backups of your MySQL database and sends it to configurable email address. The whole process is scheduled with a help of Cron, a Unix program that runs programs at scheduled times.

# Conifgure 
1. Download PHPMailer from https://github.com/PHPMailer/PHPMailer
2. Upload & rename "PHPMailer" in same directory
3. Change index.php file's credentials

# Cron Job:
```0 9 * * * php /home/your_account/mydbbackup/index.php >/dev/null 2>&1```
Numbers and asterisks are the interval part, see the cheat sheet below.
php /home/your_account/backup2mail/index.php means that PHP will execute the script, and >/dev/null 2>&1 tells Cron not to send output to e-mail specified in the first line of Cron configuration file.
Replace “your_account” with your account username, and adjust the interval (the above is everyday at midnight).

# Deny Public Access
Use following code to .htaccess
```
ErrorDocument 403 https://www.royaltechbd.com/
Order Allow,Deny
```

@echo off
REM DersPROS Cron Runner
REM Bu dosyayi Windows Gorev Zamanlayicisi'na ekleyin (her 5 dakikada bir)
C:\xampp\php\php.exe C:\xampp\htdocs\DersPros\cron_notifications.php >> C:\xampp\htdocs\DersPros\cron_log.txt 2>&1

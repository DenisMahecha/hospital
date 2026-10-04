<?php

return [
    'driver' => env('BACKUP_DRIVER', env('DB_CONNECTION', 'mysql')),
    'disk' => env('BACKUP_DISK', 'local'),
    'directory' => env('BACKUP_DIRECTORY', 'backups'),
    'mysqldump' => env('MYSQLDUMP_PATH', 'C:/xampp/mysql/bin/mysqldump.exe'),
    'sqlite3' => env('SQLITE3_PATH', 'sqlite3'),
    'timeout' => (int) env('BACKUP_TIMEOUT', 120),
];

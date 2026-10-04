<?php

namespace App\Services;

use App\Models\BackupLog;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class DatabaseBackupService
{
    public function create(): BackupLog
    {
        $name = 'mysql-'.now()->format('Ymd-His');
        $relativePath = trim(config('backup.directory'), '/').'/'.$name.'.sql';
        $disk = Storage::disk(config('backup.disk', 'local'));
        $disk->makeDirectory(config('backup.directory'));

        try {
            if (config('backup.driver') === 'log') {
                $disk->put($relativePath, "Backup test artifact\n");
            } elseif (config('backup.driver') === 'mysql') {
                $this->dumpMysql($disk->path($relativePath));
            } elseif (config('backup.driver') === 'sqlite') {
                $this->dumpSqlite($disk->path($relativePath));
            } else {
                throw new RuntimeException('Unsupported backup database driver: '.config('backup.driver'));
            }

            return BackupLog::create([
                'backup_name' => $name,
                'storage_path' => $relativePath,
                'file_size' => $disk->size($relativePath),
                'status' => 'success',
            ]);
        } catch (\Throwable $exception) {
            if ($disk->exists($relativePath)) {
                $disk->delete($relativePath);
            }

            return BackupLog::create([
                'backup_name' => $name,
                'storage_path' => $relativePath,
                'status' => 'failed',
            ]);
        }
    }

    private function dumpMysql(string $targetPath): void
    {
        $arguments = implode(' ', [
            escapeshellarg(config('backup.mysqldump')),
            '--host='.escapeshellarg(config('database.connections.mysql.host')),
            '--port='.escapeshellarg(config('database.connections.mysql.port')),
            '--user='.escapeshellarg(config('database.connections.mysql.username')),
            '--password='.escapeshellarg(config('database.connections.mysql.password')),
            escapeshellarg(config('database.connections.mysql.database')),
            '>',
            escapeshellarg($targetPath),
        ]);

        $result = Process::timeout(config('backup.timeout', 120))->run($arguments);

        if ($result->failed()) {
            throw new RuntimeException($result->errorOutput() ?: 'The database dump command failed.');
        }
    }

    private function dumpSqlite(string $targetPath): void
    {
        $database = config('database.connections.sqlite.database');
        $result = Process::timeout(config('backup.timeout', 120))->run(implode(' ', [
            escapeshellarg(config('backup.sqlite3')),
            escapeshellarg($database),
            escapeshellarg('.output '.str_replace('\\', '/', $targetPath)),
            escapeshellarg('.dump'),
        ]));

        if ($result->failed()) {
            throw new RuntimeException($result->errorOutput() ?: 'The SQLite database dump command failed.');
        }
    }
}

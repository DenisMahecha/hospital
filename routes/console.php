<?php

use App\Http\Controllers\PatientController;
use App\Services\DatabaseBackupService;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('hms:backup', function (DatabaseBackupService $backupService) {
    $backup = $backupService->create();
    $backup->status === 'success' ? $this->info('Backup created: '.$backup->storage_path) : $this->error('Backup failed.');
    return $backup->status === 'success' ? 0 : 1;
})->purpose('Create the scheduled HMS database backup');

Artisan::command('patients:purge {--force : Skip confirmation} {--dry-run : Show how many records would be removed without deleting them}', function () {
    $patientController = app(PatientController::class);

    if (! $this->option('force') && ! $this->confirm('This will permanently delete all patient records and their related clinical history. Continue?')) {
        $this->warn('Patient purge cancelled.');

        return self::FAILURE;
    }

    $count = $patientController->purgePatients();

    if ($this->option('dry-run')) {
        $this->info('Dry run: '.$count.' patient records would be removed.');

        return self::SUCCESS;
    }

    $this->info('Deleted '.$count.' patient records.');

    return self::SUCCESS;
})->purpose('Permanently delete every patient record from the system');

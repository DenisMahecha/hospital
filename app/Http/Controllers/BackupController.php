<?php

namespace App\Http\Controllers;

use App\Models\BackupLog;
use App\Services\DatabaseBackupService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class BackupController extends Controller
{
    public function index(): View { return view('backups.index', ['backups' => BackupLog::latest()->paginate(25)]); }

    public function store(DatabaseBackupService $backupService): RedirectResponse
    {
        $backup = $backupService->create();
        $message = $backup->status === 'success' ? 'Database backup created successfully.' : 'Database backup failed. Review the backup log.';

        return redirect()->route('backups.index')->with($backup->status === 'success' ? 'success' : 'error', $message);
    }
}

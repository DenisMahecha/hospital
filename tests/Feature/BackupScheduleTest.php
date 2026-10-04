<?php

namespace Tests\Feature;

use App\Models\BackupLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BackupScheduleTest extends TestCase
{
    use RefreshDatabase;

    public function test_backup_command_creates_a_backup_log(): void
    {
        config(['backup.driver' => 'log']);
        $this->artisan('hms:backup')->assertExitCode(0);
        $this->assertDatabaseHas('backup_logs', ['status' => 'success']);
    }
}
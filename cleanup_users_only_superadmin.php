<?php

require __DIR__ . '/vendor/autoload.php';

$app = require __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

$superAdmin = User::where('email', 'admin@hospital.test')->first();

if (! $superAdmin) {
    echo "TARGET_ADMIN_NOT_FOUND\n";
    exit(1);
}

$superAdmin->syncRoles(['super-admin']);

$excludedTables = [
    'users',
    'roles',
    'permissions',
    'role_has_permissions',
    'model_has_roles',
    'model_has_permissions',
    'password_reset_tokens',
    'sessions',
    'migrations',
    'cache',
    'cache_locks',
    'jobs',
    'job_batches',
    'failed_jobs',
];

$tables = [];
foreach (DB::select('SHOW TABLES') as $row) {
    $tableName = array_values((array) $row)[0];
    if (! in_array($tableName, $excludedTables, true)) {
        $tables[] = $tableName;
    }
}

DB::statement('SET FOREIGN_KEY_CHECKS = 0');
foreach ($tables as $table) {
    DB::table($table)->delete();
}
DB::statement('SET FOREIGN_KEY_CHECKS = 1');

User::whereKeyNot($superAdmin->id)->delete();
$superAdmin->refresh();
$superAdmin->syncRoles(['super-admin']);

$remaining = User::with('roles')->orderBy('id')->get();

echo 'SUPER_ADMIN_EMAIL=' . $superAdmin->email . PHP_EOL;
echo 'ROLE=' . $superAdmin->getRoleNames()->first() . PHP_EOL;
echo 'USER_COUNT=' . $remaining->count() . PHP_EOL;

foreach ($remaining as $user) {
    echo $user->id . '|' . $user->email . '|' . $user->roles->pluck('name')->join(',') . PHP_EOL;
}

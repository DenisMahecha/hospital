<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $roles = [
            'super-admin',
            'hospital-manager',
            'receptionist',
            'doctor',
            'nurse',
            'lab-technician',
            'radiologist',
            'pharmacist',
            'cashier',
            'hr',
            'store-keeper',
            'patient',
        ];

        foreach ($roles as $roleName) {
            Role::firstOrCreate(['name' => $roleName, 'guard_name' => 'web']);
        }

        $admin = User::firstOrCreate(['email' => 'admin@hospital.test'], [
            'name' => 'Hospital Admin',
            'username' => 'admin',
            'password' => Hash::make('password'),
            'is_active' => true,
        ]);

        $admin->assignRole('super-admin');

        $this->call(SampleDataSeeder::class);
    }
}

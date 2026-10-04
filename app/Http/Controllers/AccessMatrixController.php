<?php

namespace App\Http\Controllers;

use Illuminate\View\View;

class AccessMatrixController extends Controller
{
    public function index(): View
    {
        $roles = [
            'Super-admin',
            'Hospital manager',
            'Receptionist',
            'Doctor',
            'Nurse',
            'Lab technician',
            'Pharmacist',
            'Cashier',
            'HR',
        ];

        $matrix = [
            ['module' => 'Watumiaji / Roles', 'access' => ['Full', 'None', 'None', 'None', 'None', 'None', 'None', 'None', 'None']],
            ['module' => 'Usajili wa wagonjwa', 'access' => ['Full', 'View', 'Full', 'View', 'View', 'None', 'None', 'View', 'None']],
            ['module' => 'Rekodi za matibabu (EMR)', 'access' => ['View', 'View', 'None', 'Full', 'Edit', 'View', 'View', 'None', 'None']],
            ['module' => 'Matokeo ya maabara', 'access' => ['View', 'View', 'None', 'View', 'View', 'Full', 'None', 'None', 'None']],
            ['module' => 'Dawa / Stock', 'access' => ['Full', 'View', 'None', 'View', 'View', 'None', 'Full', 'None', 'None']],
            ['module' => 'Bili / Malipo', 'access' => ['Full', 'View', 'None', 'None', 'None', 'None', 'None', 'Full', 'None']],
            ['module' => 'Wafanyakazi / Payroll', 'access' => ['Full', 'View', 'None', 'None', 'None', 'None', 'None', 'None', 'Full']],
            ['module' => 'Ripoti', 'access' => ['Full', 'Full', 'None', 'Own', 'Own', 'Own', 'Own', 'Own', 'Own']],
            ['module' => 'Audit Logs', 'access' => ['Full', 'View', 'None', 'None', 'None', 'None', 'None', 'None', 'None']],
        ];

        $departmentLinkage = collect(config('department_roles.mapping'))
            ->map(function (array $roles, string $key): array {
                $label = config('department_roles.labels')[$key] ?? ucfirst(str_replace('-', ' ', $key));
                $roleLabels = array_map(fn (string $role): string => config('department_roles.role_labels')[$role] ?? ucfirst(str_replace('-', ' ', $role)), $roles);

                $modules = match ($key) {
                    'system-administration' => [
                        ['module' => 'Users & roles', 'access' => ['Super-admin' => 'Full']],
                        ['module' => 'Audit logs', 'access' => ['Super-admin' => 'Full']],
                        ['module' => 'System reports', 'access' => ['Super-admin' => 'Full']],
                    ],
                    'clinical' => [
                        ['module' => 'Patient registration', 'access' => ['Doctor' => 'View', 'Nurse' => 'View']],
                        ['module' => 'Consultation notes', 'access' => ['Doctor' => 'Full', 'Nurse' => 'Edit']],
                        ['module' => 'Admissions & wards', 'access' => ['Doctor' => 'View', 'Nurse' => 'Full']],
                        ['module' => 'Nursing care', 'access' => ['Doctor' => 'View', 'Nurse' => 'Full']],
                    ],
                    'diagnostics' => [
                        ['module' => 'Lab requests', 'access' => ['Lab technician' => 'Full', 'Radiologist' => 'View']],
                        ['module' => 'Lab results', 'access' => ['Lab technician' => 'Full', 'Radiologist' => 'View']],
                        ['module' => 'Radiology queue', 'access' => ['Lab technician' => 'None', 'Radiologist' => 'Full']],
                    ],
                    'pharmacy' => [
                        ['module' => 'Prescriptions', 'access' => ['Pharmacist' => 'View']],
                        ['module' => 'Dispensing', 'access' => ['Pharmacist' => 'Full']],
                        ['module' => 'Inventory control', 'access' => ['Pharmacist' => 'Full']],
                    ],
                    'finance' => [
                        ['module' => 'Billing', 'access' => ['Cashier' => 'Full']],
                        ['module' => 'Payments', 'access' => ['Cashier' => 'Full']],
                        ['module' => 'Insurance claims', 'access' => ['Cashier' => 'View']],
                    ],
                    'front-office' => [
                        ['module' => 'Appointments', 'access' => ['Receptionist' => 'Full']],
                        ['module' => 'Patient registration', 'access' => ['Receptionist' => 'Full']],
                        ['module' => 'Front desk records', 'access' => ['Receptionist' => 'Full']],
                    ],
                    'people-operations' => [
                        ['module' => 'Employees', 'access' => ['HR' => 'Full']],
                        ['module' => 'Payroll', 'access' => ['HR' => 'Full']],
                        ['module' => 'Staff access', 'access' => ['HR' => 'Full']],
                    ],
                    'stores' => [
                        ['module' => 'Inventory', 'access' => ['Store keeper' => 'Full']],
                        ['module' => 'Suppliers', 'access' => ['Store keeper' => 'Full']],
                        ['module' => 'Purchase orders', 'access' => ['Store keeper' => 'Full']],
                    ],
                    'management' => [
                        ['module' => 'Clinical overview', 'access' => ['Hospital manager' => 'View']],
                        ['module' => 'Reports', 'access' => ['Hospital manager' => 'Full']],
                        ['module' => 'Audit records', 'access' => ['Hospital manager' => 'View']],
                    ],
                    default => [],
                };

                return [
                    'label' => $label,
                    'summary' => 'Department-linked role access and operational scope.',
                    'roles' => $roleLabels,
                    'modules' => $modules,
                ];
            })->values()->all();

        return view('access-matrix.index', compact('roles', 'matrix', 'departmentLinkage'));
    }
}
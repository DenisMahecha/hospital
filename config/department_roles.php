<?php

return [
    'mapping' => [
        'system-administration' => ['super-admin'],
        'clinical' => ['doctor', 'nurse'],
        'diagnostics' => ['lab-technician', 'radiologist'],
        'pharmacy' => ['pharmacist'],
        'finance' => ['cashier'],
        'front-office' => ['receptionist'],
        'people-operations' => ['hr'],
        'stores' => ['store-keeper'],
        'management' => ['hospital-manager'],
    ],

    'labels' => [
        'system-administration' => 'System administration',
        'clinical' => 'Clinical',
        'diagnostics' => 'Diagnostics',
        'pharmacy' => 'Pharmacy',
        'finance' => 'Finance',
        'front-office' => 'Front office',
        'people-operations' => 'People operations',
        'stores' => 'Stores',
        'management' => 'Management',
    ],

    'role_labels' => [
        'super-admin' => 'Super-admin',
        'hospital-manager' => 'Hospital manager',
        'receptionist' => 'Receptionist',
        'doctor' => 'Doctor',
        'nurse' => 'Nurse',
        'lab-technician' => 'Lab technician',
        'radiologist' => 'Radiologist',
        'pharmacist' => 'Pharmacist',
        'cashier' => 'Cashier',
        'hr' => 'HR',
        'store-keeper' => 'Store keeper',
    ],

    'appointment_departments' => ['OPD', 'Emergency', 'RCH', 'Cardiology', 'Dental', 'Maternity'],
];

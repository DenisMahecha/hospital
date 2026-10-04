<?php

return [
    'prices' => [
        'consultation' => (float) env('BILLING_CONSULTATION_PRICE', 20000),
        'lab_default' => (float) env('BILLING_LAB_DEFAULT_PRICE', 15000),
        'lab' => [
            'CBC' => (float) env('BILLING_LAB_CBC_PRICE', 15000),
            'Malaria' => (float) env('BILLING_LAB_MALARIA_PRICE', 10000),
            'Urinalysis' => (float) env('BILLING_LAB_URINALYSIS_PRICE', 10000),
            'Blood chemistry' => (float) env('BILLING_LAB_BLOOD_CHEMISTRY_PRICE', 25000),
        ],
        'radiology' => [
            'X-Ray' => (float) env('BILLING_XRAY_PRICE', 30000),
            'Ultrasound' => (float) env('BILLING_ULTRASOUND_PRICE', 40000),
            'CT' => (float) env('BILLING_CT_PRICE', 150000),
            'MRI' => (float) env('BILLING_MRI_PRICE', 250000),
        ],
        'emergency_triage' => (float) env('BILLING_EMERGENCY_PRICE', 30000),
        'admission' => (float) env('BILLING_ADMISSION_PRICE', 50000),
        'bed_per_day' => (float) env('BILLING_BED_PER_DAY_PRICE', 30000),
    ],
];
<?php

require __DIR__ . '/vendor/autoload.php';

$app = require __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Drug;
use App\Models\DrugBatch;
use App\Models\StockMovement;
use Illuminate\Support\Facades\DB;

$drugs = [
    ['Paracetamol 500mg', 'Analgesic', 'Tablet', 250],
    ['Ibuprofen 400mg', 'Analgesic', 'Tablet', 500],
    ['Aspirin 75mg', 'Analgesic', 'Tablet', 180],
    ['Diclofenac 50mg', 'Analgesic', 'Tablet', 350],
    ['Tramadol 50mg', 'Analgesic', 'Capsule', 450],
    ['Amoxicillin 500mg', 'Antibiotic', 'Capsule', 800],
    ['Amoxicillin 250mg', 'Antibiotic', 'Capsule', 500],
    ['Ciprofloxacin 500mg', 'Antibiotic', 'Tablet', 700],
    ['Azithromycin 500mg', 'Antibiotic', 'Tablet', 950],
    ['Doxycycline 100mg', 'Antibiotic', 'Capsule', 650],
    ['Metronidazole 400mg', 'Antibiotic', 'Tablet', 450],
    ['Flucloxacillin 500mg', 'Antibiotic', 'Capsule', 600],
    ['Cephalexin 500mg', 'Antibiotic', 'Capsule', 750],
    ['Gentamicin 80mg', 'Antibiotic', 'Injection', 150],
    ['Artemether Lumefantrine', 'Antimalarial', 'Tablet', 1200],
    ['Quinine 300mg', 'Antimalarial', 'Tablet', 300],
    ['Chloroquine 250mg', 'Antimalarial', 'Tablet', 220],
    ['Omeprazole 20mg', 'Gastrointestinal', 'Capsule', 650],
    ['Pantoprazole 40mg', 'Gastrointestinal', 'Tablet', 900],
    ['Metoclopramide 10mg', 'Gastrointestinal', 'Tablet', 300],
    ['Amlodipine 10mg', 'Antihypertensive', 'Tablet', 550],
    ['Amlodipine 5mg', 'Antihypertensive', 'Tablet', 350],
    ['Losartan 50mg', 'Antihypertensive', 'Tablet', 500],
    ['Atenolol 50mg', 'Antihypertensive', 'Tablet', 420],
    ['Hydrochlorothiazide 25mg', 'Diuretic', 'Tablet', 280],
    ['Metformin 500mg', 'Antidiabetic', 'Tablet', 600],
    ['Metformin 850mg', 'Antidiabetic', 'Tablet', 750],
    ['Glibenclamide 5mg', 'Antidiabetic', 'Tablet', 260],
    ['Insulin 100IU', 'Antidiabetic', 'Injection', 80],
    ['Salbutamol 4mg', 'Respiratory', 'Tablet', 300],
    ['Salbutamol Inhaler', 'Respiratory', 'Inhaler', 1800],
    ['Aminophylline 100mg', 'Respiratory', 'Tablet', 400],
    ['Cetirizine 10mg', 'Antihistamine', 'Tablet', 400],
    ['Loratadine 10mg', 'Antihistamine', 'Tablet', 550],
    ['Chlorpheniramine 4mg', 'Antihistamine', 'Tablet', 180],
    ['Hydrocortisone 100mg', 'Corticosteroid', 'Injection', 100],
    ['Prednisolone 5mg', 'Corticosteroid', 'Tablet', 320],
    ['Dexamethasone 4mg', 'Corticosteroid', 'Injection', 250],
    ['Furosemide 40mg', 'Diuretic', 'Tablet', 250],
    ['Spironolactone 25mg', 'Diuretic', 'Tablet', 400],
    ['Diazepam 5mg', 'Sedative', 'Tablet', 200],
    ['Ringer Lactate 500ml', 'Fluid', 'Infusion', 180],
    ['Normal Saline 500ml', 'Fluid', 'Infusion', 150],
    ['Dextrose 5% 500ml', 'Fluid', 'Infusion', 160],
    ['Vitamin C 500mg', 'Supplement', 'Tablet', 500],
    ['Ferrous Sulphate 200mg', 'Supplement', 'Tablet', 280],
    ['Folic Acid 5mg', 'Supplement', 'Tablet', 120],
    ['Calcium Carbonate 500mg', 'Supplement', 'Tablet', 300],
    ['Zinc Sulphate 20mg', 'Supplement', 'Tablet', 220],
    ['Oral Rehydration Salts', 'Rehydration', 'Sachet', 100],
];

DB::transaction(function () use ($drugs): void {
    foreach ($drugs as $index => [$name, $category, $form, $unitPrice]) {
        $drug = Drug::updateOrCreate(
            ['name' => 'Test - ' . $name],
            [
                'category' => $category,
                'form' => $form,
                'unit_price' => $unitPrice,
                'stock_quantity' => 50,
                'expiry_date' => now()->addYear()->toDateString(),
                'status' => 'active',
            ]
        );

        $batch = DrugBatch::updateOrCreate(
            ['drug_id' => $drug->id, 'batch_number' => 'TEST-BATCH-' . str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT)],
            [
                'quantity' => 50,
                'expiry_date' => now()->addYear()->toDateString(),
                'unit_cost' => $unitPrice,
                'status' => 'active',
            ]
        );

        StockMovement::firstOrCreate(
            ['reference' => 'TEST-OPENING-STOCK-' . str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT)],
            [
                'drug_id' => $drug->id,
                'drug_batch_id' => $batch->id,
                'movement_type' => 'in',
                'quantity' => 50,
                'notes' => 'Test opening stock',
            ]
        );
    }
});

echo 'TEST_DRUGS=' . count($drugs) . PHP_EOL;
echo 'TOTAL_TEST_STOCK=' . Drug::where('name', 'like', 'Test - %')->sum('stock_quantity') . PHP_EOL;
echo 'TOTAL_TEST_BATCH_STOCK=' . DrugBatch::whereHas('drug', fn ($query) => $query->where('name', 'like', 'Test - %'))->sum('quantity') . PHP_EOL;

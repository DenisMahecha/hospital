<?php

namespace Database\Seeders;

use App\Models\Admission;
use App\Models\Appointment;
use App\Models\AuditLog;
use App\Models\BackupLog;
use App\Models\Drug;
use App\Models\Employee;
use App\Models\InsuranceClaim;
use App\Models\InventoryItem;
use App\Models\Invoice;
use App\Models\LabRequest;
use App\Models\Notification;
use App\Models\Patient;
use App\Models\PatientPortalAccount;
use App\Models\Payment;
use App\Models\Payroll;
use App\Models\Prescription;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\RadiologyRequest;
use App\Models\VaccinationRecord;
use App\Models\StockMovement;
use App\Models\Supplier;
use App\Models\TheatreSchedule;
use App\Models\User;
use App\Models\Visit;
use App\Models\Ward;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class SampleDataSeeder extends Seeder
{
    public function run(): void
    {
        $password = Hash::make('11223344');
        $departments = [
            ['name' => 'Hospital Manager', 'email' => 'manager@hospital.test', 'role' => 'hospital-manager', 'department' => 'management'],
            ['name' => 'Doctor Amina Mushi', 'email' => 'doctor@hospital.test', 'role' => 'doctor', 'department' => 'clinical'],
            ['name' => 'Nurse Grace Mkwawa', 'email' => 'nurse@hospital.test', 'role' => 'nurse', 'department' => 'clinical'],
            ['name' => 'Lab Technician John', 'email' => 'lab@hospital.test', 'role' => 'lab-technician', 'department' => 'diagnostics'],
            ['name' => 'Radiologist Hassan', 'email' => 'radiology@hospital.test', 'role' => 'radiologist', 'department' => 'diagnostics'],
            ['name' => 'Pharmacist Rehema', 'email' => 'pharmacy@hospital.test', 'role' => 'pharmacist', 'department' => 'pharmacy'],
            ['name' => 'Cashier Peter', 'email' => 'cashier@hospital.test', 'role' => 'cashier', 'department' => 'finance'],
            ['name' => 'HR Officer Mariam', 'email' => 'hr@hospital.test', 'role' => 'hr', 'department' => 'people-operations'],
            ['name' => 'Store Keeper David', 'email' => 'store@hospital.test', 'role' => 'store-keeper', 'department' => 'stores'],
            ['name' => 'Receptionist Neema', 'email' => 'reception@hospital.test', 'role' => 'receptionist', 'department' => 'front-office'],
        ];
        $users = [];
        foreach ($departments as $department) {
            for ($number = 1; $number <= 3; $number++) {
                $email = $number === 1
                    ? $department['email']
                    : str_replace('@hospital.test', "-{$number}@hospital.test", $department['email']);
                $user = User::updateOrCreate(['email' => $email], [
                    'name' => $number === 1 ? $department['name'] : $department['name'] . " {$number}",
                    'username' => str_replace('@hospital.test', '', $email),
                    'department' => $department['department'],
                    'password' => $password,
                    'is_active' => true,
                ]);
                $user->syncRoles([$department['role']]);
                $users[$department['role']] = $users[$department['role']] ?? $user;
            }
        }

        $patientDetails = [
            ['Amina', 'Mushi', 'female', '1995-03-12', 'Dar es Salaam', 'NHIF'],
            ['Moses', 'Sanga', 'male', '1988-02-03', 'Dodoma', 'Jubilee'],
            ['Zawadi', 'Mwalimu', 'female', '1998-05-15', 'Mwanza', 'Private'],
        ];
        $patients = [];
        for ($number = 1; $number <= 20; $number++) {
            $details = $patientDetails[($number - 1) % count($patientDetails)];
            $patients[] = Patient::firstOrCreate(
                ['patient_number' => sprintf('PT-DEMO-%03d', $number)],
                [
                    'first_name' => $number <= 3 ? $details[0] : $details[0] . " {$number}",
                    'last_name' => $details[1],
                    'gender' => $details[2],
                    'date_of_birth' => $details[3],
                    'phone' => '+255712345' . str_pad((string) $number, 3, '0', STR_PAD_LEFT),
                    'address' => $details[4],
                    'insurance_provider' => $details[5],
                    'is_active' => true,
                ]
            );
        }

        $visit = Visit::firstOrCreate(['visit_number' => 'VIS-DEMO-001'], ['patient_id' => $patients[0]->id, 'visit_date' => now(), 'department' => 'OPD', 'doctor_id' => $users['doctor']->id, 'complaint' => 'Routine review', 'status' => 'completed']);
        $employee = Employee::firstOrCreate(['employee_number' => 'EMP-DEMO-001'], ['first_name' => 'Mariam', 'last_name' => 'Kassim', 'role' => 'Nurse', 'department' => 'Clinical', 'status' => 'active']);
        Appointment::firstOrCreate(['appointment_number' => 'APT-DEMO-001'], ['patient_id' => $patients[1]->id, 'booked_by' => $users['receptionist']->id, 'scheduled_at' => now()->addDay()->setTime(10, 0), 'department' => 'OPD', 'reason' => 'Follow-up consultation', 'status' => 'scheduled']);
        Prescription::firstOrCreate(['prescription_number' => 'RX-DEMO-001'], ['patient_id' => $patients[0]->id, 'prescribed_by' => $users['doctor']->id, 'visit_id' => $visit->id, 'medication_name' => 'Paracetamol 500mg', 'dosage' => '1 tablet', 'frequency' => 'Three times daily', 'duration' => '5 days', 'status' => 'active']);
        LabRequest::firstOrCreate(['request_number' => 'LAB-DEMO-001'], ['patient_id' => $patients[0]->id, 'requested_by' => $users['doctor']->id, 'test_name' => 'Complete Blood Count', 'status' => 'pending']);
        RadiologyRequest::firstOrCreate(['request_number' => 'RAD-DEMO-001'], ['patient_id' => $patients[1]->id, 'requested_by' => $employee->id, 'modality' => 'X-Ray', 'status' => 'pending']);

        $drugs = [
            ['Paracetamol 500mg', 'Analgesic', 'Tablet', 250], ['Ibuprofen 400mg', 'Analgesic', 'Tablet', 500],
            ['Amoxicillin 500mg', 'Antibiotic', 'Capsule', 800], ['Ciprofloxacin 500mg', 'Antibiotic', 'Tablet', 700],
            ['Metronidazole 400mg', 'Antibiotic', 'Tablet', 450], ['Artemether Lumefantrine', 'Antimalarial', 'Tablet', 1200],
            ['Omeprazole 20mg', 'Gastrointestinal', 'Capsule', 650], ['Amlodipine 10mg', 'Antihypertensive', 'Tablet', 550],
            ['Losartan 50mg', 'Antihypertensive', 'Tablet', 500], ['Metformin 500mg', 'Antidiabetic', 'Tablet', 600],
            ['Insulin 100IU', 'Antidiabetic', 'Injection', 80], ['Salbutamol 4mg', 'Respiratory', 'Tablet', 300],
            ['Cetirizine 10mg', 'Antihistamine', 'Tablet', 400], ['Diclofenac 50mg', 'Analgesic', 'Tablet', 350],
            ['Furosemide 40mg', 'Diuretic', 'Tablet', 250], ['Hydrocortisone 100mg', 'Corticosteroid', 'Injection', 100],
            ['Gentamicin 80mg', 'Antibiotic', 'Injection', 150], ['Diazepam 5mg', 'Sedative', 'Tablet', 200],
            ['Ringer Lactate 500ml', 'Fluid', 'Infusion', 180], ['Vitamin C 500mg', 'Supplement', 'Tablet', 500],
        ];
        foreach ($drugs as $index => $drugDetails) {
            $drug = Drug::firstOrCreate(['name' => $drugDetails[0]], [
                'category' => $drugDetails[1], 'form' => $drugDetails[2], 'unit_price' => $drugDetails[3],
                'stock_quantity' => 120, 'expiry_date' => now()->addYear(), 'status' => 'active',
            ]);
            StockMovement::firstOrCreate(['drug_id' => $drug->id, 'reference' => 'DEMO-OPENING-STOCK-' . str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT)], ['movement_type' => 'in', 'quantity' => 120, 'notes' => 'Demo opening stock']);
        }

        $ward = Ward::firstOrCreate(['name' => 'Demo General Ward'], ['capacity' => 30, 'status' => 'available']);
        $admission = Admission::firstOrCreate(['admission_number' => 'ADM-DEMO-001'], ['patient_id' => $patients[1]->id, 'ward_id' => $ward->id, 'admission_date' => now(), 'status' => 'admitted', 'notes' => 'Demo admission']);
        $invoice = Invoice::firstOrCreate(['invoice_number' => 'INV-DEMO-001'], ['patient_id' => $patients[1]->id, 'admission_id' => $admission->id, 'total_amount' => 350000, 'status' => 'pending']);
        Payment::firstOrCreate(['invoice_id' => $invoice->id, 'payment_method' => 'mobile_money', 'reference' => 'DEMO-TX-001'], ['received_by' => $users['cashier']->id, 'amount' => 100000, 'paid_at' => now(), 'status' => 'completed']);
        InsuranceClaim::firstOrCreate(['claim_number' => 'CLM-DEMO-001'], ['patient_id' => $patients[1]->id, 'invoice_id' => $invoice->id, 'provider' => 'Jubilee', 'amount' => 250000, 'status' => 'submitted']);

        $supplier = Supplier::firstOrCreate(['name' => 'Demo Medical Supplies'], ['contact_person' => 'Jane Mushi', 'phone' => '+255755000111', 'email' => 'demo@supplier.test', 'status' => 'active']);
        $item = InventoryItem::firstOrCreate(['item_code' => 'INV-DEMO-001'], ['name' => 'Surgical gloves', 'category' => 'Consumables', 'unit' => 'box', 'reorder_level' => 10, 'current_stock' => 40, 'status' => 'active', 'supplier_id' => $supplier->id]);
        $order = PurchaseOrder::firstOrCreate(['po_number' => 'PO-DEMO-001'], ['supplier_id' => $supplier->id, 'ordered_by' => $users['store-keeper']->id, 'order_date' => today(), 'status' => 'approved', 'total_amount' => 150000]);
        PurchaseOrderItem::firstOrCreate(['purchase_order_id' => $order->id, 'inventory_item_id' => $item->id], ['quantity' => 10, 'unit_cost' => 15000, 'total_cost' => 150000]);

        Payroll::firstOrCreate(['employee_id' => $employee->id, 'month' => now()->format('Y-m')], ['basic_salary' => 1800000, 'allowances' => 200000, 'deductions' => 100000, 'net_salary' => 1900000, 'status' => 'approved']);
        TheatreSchedule::firstOrCreate(['surgery_name' => 'Demo Appendectomy'], ['scheduled_date' => now()->addDays(3)->setTime(9, 0), 'theatre_room' => 'OT-1', 'status' => 'scheduled']);
        $vaccines = ['BCG', 'OPV', 'Pentavalent', 'PCV', 'Rotavirus', 'Measles-Rubella', 'Yellow Fever', 'HPV'];
        for ($number = 1; $number <= 20; $number++) {
            VaccinationRecord::firstOrCreate(
                ['child_name' => "Child Demo {$number}", 'vaccine_name' => $vaccines[($number - 1) % count($vaccines)]],
                ['date_of_birth' => now()->subYears(1)->subDays($number), 'dose_number' => (($number - 1) % 3) + 1, 'status' => 'completed']
            );
        }

        $patientUser = User::updateOrCreate(['email' => 'patient@hospital.test'], ['name' => 'Demo Patient', 'username' => 'patient', 'password' => $password, 'is_active' => true]);
        $patientUser->syncRoles(['patient']);
        PatientPortalAccount::firstOrCreate(['portal_username' => 'demo.patient'], ['patient_id' => $patients[0]->id, 'user_id' => $patientUser->id, 'status' => 'active']);
        Notification::firstOrCreate(['user_id' => $users['doctor']->id, 'title' => 'Demo lab alert'], ['message' => 'A demo lab request is waiting for review.', 'type' => 'info']);
        AuditLog::firstOrCreate(['user_id' => $users['doctor']->id, 'action' => 'seed', 'module_name' => 'demo'], ['description' => 'Demo data loaded for testing.']);
        BackupLog::firstOrCreate(['backup_name' => 'demo-backup'], ['storage_path' => 'backups/demo.sql', 'file_size' => 0, 'status' => 'success']);
    }
}

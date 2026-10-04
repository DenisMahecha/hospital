<?php

use App\Http\Controllers\AdmissionController;
use App\Http\Controllers\AppointmentController;
use App\Http\Controllers\AccessMatrixController;
use App\Http\Controllers\AuditLogController;
use App\Http\Controllers\AuthenticationController;
use App\Http\Controllers\BillingController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DeathRecordController;
use App\Http\Controllers\ZoneDashboardController;
use App\Http\Controllers\EmergencyController;
use App\Http\Controllers\HrController;
use App\Http\Controllers\InventoryController;
use App\Http\Controllers\BackupController;
use App\Http\Controllers\LabController;
use App\Http\Controllers\PatientController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\PasswordResetController;
use App\Http\Controllers\PortalController;
use App\Http\Controllers\PharmacyController;
use App\Http\Controllers\PrescriptionController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\PurchaseOrderController;
use App\Http\Controllers\SupplierController;
use App\Http\Controllers\UserAdministrationController;
use App\Http\Controllers\RadiologyController;
use App\Http\Controllers\RchController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\TheatreController;
use App\Http\Controllers\WardController;
use App\Http\Controllers\VisitController;
use App\Http\Controllers\InsuranceClaimController;
use App\Http\Controllers\ReconciliationController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/login', [AuthenticationController::class, 'create'])->name('login');
Route::post('/login', [AuthenticationController::class, 'store'])->name('login.store');
Route::get('/login/2fa', [AuthenticationController::class, 'twoFactorForm'])->name('login.2fa');
Route::post('/login/2fa', [AuthenticationController::class, 'verifyTwoFactor'])->name('login.2fa.verify');
Route::get('/forgot-password', [PasswordResetController::class, 'request'])->name('password.request');
Route::post('/forgot-password', [PasswordResetController::class, 'email'])->name('password.email');
Route::get('/reset-password/{token}', [PasswordResetController::class, 'resetForm'])->name('password.reset');
Route::post('/reset-password', [PasswordResetController::class, 'reset'])->name('password.update');

Route::middleware('auth')->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/users', [UserAdministrationController::class, 'index'])->middleware(['role:super-admin', 'department:system-administration'])->name('users.index');
    Route::get('/users/create', [UserAdministrationController::class, 'create'])->middleware(['role:super-admin', 'department:system-administration'])->name('users.create');
    Route::post('/users', [UserAdministrationController::class, 'store'])->middleware(['role:super-admin', 'department:system-administration'])->name('users.store');
    Route::get('/users/{user}/edit', [UserAdministrationController::class, 'edit'])->middleware(['role:super-admin', 'department:system-administration'])->name('users.edit');
    Route::put('/users/{user}', [UserAdministrationController::class, 'update'])->middleware(['role:super-admin', 'department:system-administration'])->name('users.update');
    Route::post('/users/{user}/toggle-status', [UserAdministrationController::class, 'toggleStatus'])->middleware(['role:super-admin', 'department:system-administration'])->name('users.toggle-status');
    Route::post('/users/patient-portal', [UserAdministrationController::class, 'storePatientPortalAccount'])->middleware(['role:super-admin', 'department:system-administration'])->name('users.patient-portal.store');
    Route::get('/access-matrix', [AccessMatrixController::class, 'index'])->middleware(['role:super-admin', 'department:system-administration'])->name('access-matrix.index');

    Route::get('/patients', [PatientController::class, 'index'])->middleware(['role:super-admin|hospital-manager|receptionist|doctor|lab-technician|cashier', 'department:management,front-office,clinical,diagnostics,finance'])->name('patients.index');
    Route::get('/patients/{patient}/timeline', [PatientController::class, 'timeline'])->middleware(['role:super-admin|hospital-manager|receptionist|doctor|lab-technician|cashier', 'department:management,front-office,clinical,diagnostics,finance'])->name('patients.timeline');
    Route::get('/patients/create', [PatientController::class, 'create'])->middleware(['role:super-admin|receptionist', 'department:management,front-office'])->name('patients.create');
    Route::post('/patients', [PatientController::class, 'store'])->middleware(['role:super-admin|receptionist', 'department:management,front-office'])->name('patients.store');
    Route::delete('/patients', [PatientController::class, 'destroyAll'])->middleware(['role:super-admin', 'department:system-administration'])->name('patients.destroy-all');
    Route::get('/appointments', [AppointmentController::class, 'index'])->middleware(['role:super-admin|hospital-manager|receptionist|doctor', 'department:management,front-office,clinical'])->name('appointments.index');
    Route::get('/appointments/queue', [AppointmentController::class, 'queue'])->middleware(['role:super-admin|hospital-manager|receptionist|doctor', 'department:management,front-office,clinical'])->name('appointments.queue');
    Route::get('/appointments/patient-suggestions', [AppointmentController::class, 'patientSuggestions'])->middleware(['role:super-admin|hospital-manager|receptionist|doctor', 'department:management,front-office,clinical'])->name('appointments.patient-suggestions');
    Route::get('/appointments/create', [AppointmentController::class, 'create'])->middleware(['role:super-admin|hospital-manager|receptionist|doctor', 'department:management,front-office,clinical'])->name('appointments.create');
    Route::post('/appointments', [AppointmentController::class, 'store'])->middleware(['role:super-admin|hospital-manager|receptionist|doctor', 'department:management,front-office,clinical'])->name('appointments.store');
    Route::patch('/appointments/{appointment}/status', [AppointmentController::class, 'updateStatus'])->middleware(['role:super-admin|hospital-manager|receptionist|doctor', 'department:management,front-office,clinical'])->name('appointments.status');
    Route::patch('/appointments/{appointment}/reschedule', [AppointmentController::class, 'reschedule'])->middleware(['role:super-admin|hospital-manager|receptionist|doctor', 'department:management,front-office,clinical'])->name('appointments.reschedule');
    Route::get('/prescriptions', [PrescriptionController::class, 'index'])->middleware(['role:super-admin|hospital-manager|doctor|pharmacist', 'department:management,clinical,pharmacy'])->name('prescriptions.index');
    Route::get('/prescriptions/drug-suggestions', [PrescriptionController::class, 'drugSuggestions'])->middleware(['role:super-admin|hospital-manager|doctor|pharmacist', 'department:management,clinical,pharmacy'])->name('prescriptions.drug-suggestions');
    Route::post('/prescriptions', [PrescriptionController::class, 'store'])->middleware(['role:super-admin|hospital-manager|doctor', 'department:management,clinical,pharmacy'])->name('prescriptions.store');
    Route::get('/visits', [VisitController::class, 'index'])->middleware(['role:super-admin|hospital-manager|doctor', 'department:management,clinical'])->name('visits.index');
    Route::post('/visits', [VisitController::class, 'store'])->middleware(['role:super-admin|doctor', 'department:clinical'])->name('visits.store');
    Route::post('/visits/{visit}/vitals', [VisitController::class, 'storeVitals'])->middleware(['role:super-admin|doctor', 'department:clinical'])->name('visits.vitals.store');
    Route::get('/doctor-zone', [DashboardController::class, 'doctorZone'])->middleware(['role:super-admin|doctor', 'department:clinical'])->name('doctor.zone');

    Route::get('/lab', [LabController::class, 'index'])->middleware(['role:super-admin|hospital-manager|lab-technician', 'department:management,diagnostics'])->name('lab.index');
    Route::post('/lab', [LabController::class, 'store'])->middleware(['role:super-admin', 'department:diagnostics'])->name('lab.store');
    Route::post('/lab/{labRequest}/complete', [LabController::class, 'complete'])->middleware(['role:super-admin|lab-technician', 'department:diagnostics'])->name('lab.complete');
    Route::post('/lab/{labRequest}/status', [LabController::class, 'updateSampleStatus'])->middleware(['role:super-admin|lab-technician', 'department:diagnostics'])->name('lab.status');
    Route::post('/lab/{labRequest}/amend', [LabController::class, 'amend'])->middleware(['role:super-admin|lab-technician', 'department:diagnostics'])->name('lab.amend');
    Route::get('/lab/{labRequest}/amend', [LabController::class, 'amendForm'])->middleware(['role:super-admin|lab-technician', 'department:diagnostics'])->name('lab.amend.form');
    Route::get('/lab/{labRequest}/history', [LabController::class, 'history'])->middleware(['role:super-admin|hospital-manager|lab-technician', 'department:management,diagnostics'])->name('lab.history');
    Route::get('/pharmacy', [PharmacyController::class, 'index'])->middleware(['role:super-admin|hospital-manager|doctor|pharmacist', 'department:management,clinical,pharmacy'])->name('pharmacy.index');
    Route::get('/pharmacy/alerts', [PharmacyController::class, 'alerts'])->middleware(['role:super-admin|hospital-manager|pharmacist', 'department:management,pharmacy'])->name('pharmacy.alerts');
    Route::get('/pharmacy/catalogue', [PharmacyController::class, 'catalogue'])->middleware(['role:super-admin|pharmacist', 'department:pharmacy'])->name('pharmacy.catalogue');
    Route::get('/pharmacy/history', [PharmacyController::class, 'history'])->middleware(['role:super-admin|hospital-manager|pharmacist', 'department:management,pharmacy'])->name('pharmacy.history');
    Route::get('/pharmacy/dispensing/{prescription}/receipt', [PharmacyController::class, 'receipt'])->middleware(['role:super-admin|pharmacist', 'department:pharmacy'])->name('pharmacy.receipt');
    Route::post('/pharmacy/dispense', [PharmacyController::class, 'dispense'])->middleware(['role:super-admin|pharmacist', 'department:pharmacy'])->name('pharmacy.dispense');
    Route::post('/pharmacy/drugs', [PharmacyController::class, 'storeDrug'])->middleware(['role:super-admin|pharmacist', 'department:pharmacy'])->name('pharmacy.drugs.store');
    Route::patch('/pharmacy/drugs/{drug}', [PharmacyController::class, 'updateDrug'])->middleware(['role:super-admin|pharmacist', 'department:pharmacy'])->name('pharmacy.drugs.update');
    Route::post('/pharmacy/receive', [PharmacyController::class, 'receive'])->middleware(['role:super-admin|pharmacist', 'department:pharmacy'])->name('pharmacy.receive');
    Route::post('/pharmacy/adjust', [PharmacyController::class, 'adjust'])->middleware(['role:super-admin|pharmacist', 'department:pharmacy'])->name('pharmacy.adjust');
    Route::get('/reports', [ReportController::class, 'index'])->middleware(['role:super-admin|hospital-manager|doctor|lab-technician|pharmacist|cashier|hr|store-keeper', 'department:management,clinical,diagnostics,pharmacy,finance,people-operations,stores'])->name('reports.index');
    Route::get('/reports/export', [ReportController::class, 'export'])->middleware(['role:super-admin|hospital-manager|doctor|lab-technician|pharmacist|cashier|hr|store-keeper', 'department:management,clinical,diagnostics,pharmacy,finance,people-operations,stores'])->name('reports.export');

    Route::get('/wards', [WardController::class, 'index'])->middleware('role:super-admin|hospital-manager|doctor|nurse')->name('wards.index');
    Route::post('/wards', [WardController::class, 'store'])->middleware('role:super-admin|hospital-manager')->name('wards.store');
    Route::patch('/wards/{ward}', [WardController::class, 'update'])->middleware('role:super-admin|hospital-manager')->name('wards.update');
    Route::get('/admissions', [AdmissionController::class, 'index'])->middleware('role:super-admin|hospital-manager|doctor|nurse')->name('admissions.index');
    Route::get('/admissions/create', [AdmissionController::class, 'create'])->middleware('role:super-admin|doctor|nurse')->name('admissions.create');
    Route::get('/nurse/vitals', [AdmissionController::class, 'nurseVitalsIndex'])->middleware('role:super-admin|nurse')->name('nurse.vitals.index');
    Route::post('/nurse/vitals', [AdmissionController::class, 'storeNurseVitals'])->middleware('role:super-admin|nurse')->name('nurse.vitals.store');
    Route::get('/nursing-care', [AdmissionController::class, 'nursingCareIndex'])->middleware('role:super-admin|nurse')->name('nursing-care.index');
    Route::post('/nursing-care', [AdmissionController::class, 'storeNursingCareNote'])->middleware('role:super-admin|nurse')->name('nursing-care.notes.store');
    Route::post('/admissions', [AdmissionController::class, 'store'])->middleware('role:super-admin|doctor|nurse')->name('admissions.store');
    Route::post('/admissions/{admission}/discharge', [AdmissionController::class, 'discharge'])->middleware('role:super-admin|doctor|nurse')->name('admissions.discharge');
    Route::post('/admissions/{admission}/nursing-notes', [AdmissionController::class, 'nursingNote'])->middleware('role:super-admin|nurse')->name('admissions.nursing-note');
    Route::post('/admissions/notes', [AdmissionController::class, 'storeNursingCareNote'])->middleware('role:super-admin|nurse')->name('admissions.notes.store');
    Route::post('/admissions/{admission}/vitals', [AdmissionController::class, 'admissionVital'])->middleware('role:super-admin|nurse')->name('admissions.vitals');
    Route::post('/admissions/{admission}/medications', [AdmissionController::class, 'medicationAdministration'])->middleware('role:super-admin|nurse')->name('admissions.medications');
    Route::post('/admissions/{admission}/transfer', [AdmissionController::class, 'transfer'])->middleware('role:super-admin|nurse')->name('admissions.transfer');
    Route::post('/admissions/{admission}/ward-rounds', [AdmissionController::class, 'wardRound'])->middleware('role:super-admin|doctor|nurse')->name('admissions.ward-round');
    Route::get('/billing', [BillingController::class, 'index'])->middleware(['role:super-admin|hospital-manager|cashier', 'department:management,finance'])->name('billing.index');
    Route::get('/billing/patient-suggestions', [BillingController::class, 'patientSuggestions'])->middleware(['role:super-admin|hospital-manager|cashier', 'department:management,finance'])->name('billing.patient-suggestions');
    Route::get('/billing/patient-services', [BillingController::class, 'patientServices'])->middleware(['role:super-admin|hospital-manager|cashier', 'department:management,finance'])->name('billing.patient-services');
    Route::get('/billing/collections', [BillingController::class, 'collections'])->middleware(['role:super-admin|hospital-manager|cashier', 'department:management,finance'])->name('billing.collections');
    Route::post('/billing', [BillingController::class, 'store'])->middleware(['role:super-admin|cashier', 'department:finance'])->name('billing.store');
    Route::post('/billing/journey', [BillingController::class, 'generateJourneyInvoice'])->middleware(['role:super-admin|cashier', 'department:finance'])->name('billing.journey');
    Route::get('/payments', [PaymentController::class, 'index'])->middleware(['role:super-admin|hospital-manager|cashier', 'department:management,finance'])->name('payments.index');
    Route::get('/payments/system-total', [ReconciliationController::class, 'systemTotal'])->middleware(['role:super-admin|cashier', 'department:finance'])->name('payments.system-total');
    Route::post('/payments', [PaymentController::class, 'store'])->middleware(['role:super-admin|cashier', 'department:finance'])->name('payments.store');
    Route::get('/payments/{payment}/receipt', [PaymentController::class, 'receipt'])->middleware(['role:super-admin|cashier', 'department:finance'])->name('payments.receipt');
    Route::get('/payments/{payment}/receipt.pdf', [PaymentController::class, 'receiptPdf'])->middleware(['role:super-admin|cashier', 'department:finance'])->name('payments.receipt.pdf');
    Route::get('/insurance', [InsuranceClaimController::class, 'index'])->middleware(['role:super-admin|hospital-manager|cashier', 'department:management,finance'])->name('insurance.index');
    Route::post('/insurance', [InsuranceClaimController::class, 'store'])->middleware(['role:super-admin|cashier', 'department:finance'])->name('insurance.store');
    Route::post('/insurance/{claim}/review', [InsuranceClaimController::class, 'review'])->middleware(['role:super-admin|hospital-manager', 'department:management'])->name('insurance.review');
    Route::post('/reconciliation', [ReconciliationController::class, 'store'])->middleware(['role:super-admin|cashier', 'department:finance'])->name('reconciliation.store');
    Route::get('/emergency', [EmergencyController::class, 'index'])->middleware('role:super-admin|hospital-manager|doctor')->name('emergency.index');
    Route::post('/emergency', [EmergencyController::class, 'store'])->middleware('role:super-admin|doctor')->name('emergency.store');
    Route::post('/emergency/{emergencyCase}/close', [EmergencyController::class, 'close'])->middleware('role:super-admin|doctor')->name('emergency.close');

    Route::get('/hr', [HrController::class, 'index'])->middleware(['role:super-admin|hospital-manager|hr', 'department:management,people-operations'])->name('hr.index');
    Route::post('/hr/employees', [HrController::class, 'storeEmployee'])->middleware(['role:super-admin|hr', 'department:people-operations'])->name('hr.employees.store');
    Route::post('/hr/payroll', [HrController::class, 'storePayroll'])->middleware(['role:super-admin|hr', 'department:people-operations'])->name('hr.payroll.store');
    Route::get('/radiology', [RadiologyController::class, 'index'])->middleware('role:super-admin|hospital-manager|doctor|radiologist')->name('radiology.index');
    Route::post('/radiology', [RadiologyController::class, 'store'])->middleware('role:super-admin|doctor')->name('radiology.store');
    Route::post('/radiology/{radiologyRequest}/report', [RadiologyController::class, 'report'])->middleware('role:super-admin|radiologist')->name('radiology.report');
    Route::get('/radiology/{radiologyRequest}/attachment', [RadiologyController::class, 'attachment'])->middleware('role:super-admin|hospital-manager|doctor|radiologist')->name('radiology.attachment');
    Route::get('/theatre', [TheatreController::class, 'index'])->middleware('role:super-admin|hospital-manager|doctor')->name('theatre.index');
    Route::post('/theatre', [TheatreController::class, 'store'])->middleware('role:super-admin|hospital-manager|doctor')->name('theatre.store');
    Route::get('/rch', [RchController::class, 'index'])->middleware('role:super-admin|hospital-manager|doctor')->name('rch.index');
    Route::post('/rch', [RchController::class, 'store'])->middleware('role:super-admin|hospital-manager|doctor')->name('rch.store');

    Route::get('/inventory', [InventoryController::class, 'index'])
        ->middleware(['role:super-admin|store-keeper', 'department:stores'])
        ->name('inventory.index');
    Route::get('/inventory/alerts', [InventoryController::class, 'alerts'])
        ->middleware(['role:super-admin|store-keeper', 'department:stores'])
        ->name('inventory.alerts');
    Route::get('/inventory/history', [InventoryController::class, 'history'])
        ->middleware('role:super-admin|store-keeper')
        ->name('inventory.history');
    Route::post('/inventory', [InventoryController::class, 'store'])
        ->middleware('role:super-admin|store-keeper')
        ->name('inventory.store');
    Route::patch('/inventory/{inventoryItem}', [InventoryController::class, 'update'])
        ->middleware('role:super-admin|store-keeper')
        ->name('inventory.update');
    Route::post('/inventory/{inventoryItem}/adjust', [InventoryController::class, 'adjust'])
        ->middleware('role:super-admin|store-keeper')
        ->name('inventory.adjust');
    Route::get('/suppliers', [SupplierController::class, 'index'])->middleware('role:super-admin|store-keeper')->name('suppliers.index');
    Route::post('/suppliers', [SupplierController::class, 'store'])->middleware('role:super-admin|store-keeper')->name('suppliers.store');
    Route::patch('/suppliers/{supplier}', [SupplierController::class, 'update'])->middleware('role:super-admin|store-keeper')->name('suppliers.update');
    Route::get('/purchase-orders', [PurchaseOrderController::class, 'index'])->middleware('role:super-admin|store-keeper')->name('purchase-orders.index');
    Route::post('/purchase-orders', [PurchaseOrderController::class, 'store'])->middleware('role:super-admin|store-keeper')->name('purchase-orders.store');
    Route::post('/purchase-orders/{purchaseOrder}/approve', [PurchaseOrderController::class, 'approve'])->middleware('role:super-admin|store-keeper')->name('purchase-orders.approve');
    Route::post('/purchase-orders/{purchaseOrder}/receive', [PurchaseOrderController::class, 'receive'])->middleware('role:super-admin|store-keeper')->name('purchase-orders.receive');
    Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::post('/notifications', [NotificationController::class, 'store'])->middleware('role:super-admin')->name('notifications.store');
    Route::post('/notifications/{notification}/read', [NotificationController::class, 'markRead'])->name('notifications.read');
    Route::get('/audit-logs', [AuditLogController::class, 'index'])->middleware('role:super-admin')->name('audit-logs.index');
    Route::get('/backups', [BackupController::class, 'index'])->middleware('role:super-admin')->name('backups.index');
    Route::post('/backups', [BackupController::class, 'store'])->middleware('role:super-admin')->name('backups.store');
    Route::get('/deaths', [DeathRecordController::class, 'index'])->middleware('role:super-admin|hospital-manager|doctor|nurse')->name('deaths.index');
    Route::post('/deaths', [DeathRecordController::class, 'store'])->middleware('role:super-admin|doctor|nurse')->name('deaths.store');
    Route::get('/clinical-zones', [ZoneDashboardController::class, 'index'])->middleware('role:super-admin|hospital-manager|doctor|nurse')->name('zones.index');
    Route::get('/portal', [PortalController::class, 'index'])
        ->middleware('role:super-admin|patient')
        ->name('portal.index');
    Route::get('/portal/lab-results/{labRequest}', [PortalController::class, 'labResult'])
        ->middleware('role:patient')
        ->name('portal.lab-result');
    Route::get('/portal/radiology-results/{radiologyRequest}', [PortalController::class, 'radiologyResult'])
        ->middleware('role:patient')
        ->name('portal.radiology-result');
    Route::post('/portal/{portalAccount}/toggle-link', [PortalController::class, 'toggleLink'])->middleware('role:super-admin')->name('portal.toggle-link');
    Route::post('/logout', [AuthenticationController::class, 'destroy'])->name('logout');
});

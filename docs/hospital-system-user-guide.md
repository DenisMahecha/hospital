# Hospital Management System – Complete Documentation

## 1. Overview

This project is a hospital management system built with Laravel and MySQL. It covers clinical operations, patient management, billing, admissions, laboratory, radiology, pharmacy, inventory, HR, notifications, and reporting.

The system is designed for a multi-role hospital environment where each department has a dedicated dashboard and permission boundaries.

### Core goals
- Manage patients, visits, admissions, and ward occupancy
- Support role-based access for different departments
- Track clinical, financial, and operational performance
- Provide reports for leadership and department heads
- Support patient portal-style access for patient users
- Maintain audit logs and system traceability

---

## 2. System architecture

### 2.1 Technology stack
- PHP 8.2+
- Laravel 12
- MySQL
- Blade templates
- Spatie Permission for role management
- Eloquent ORM
- Artisan CLI
- XAMPP for local development

### 2.2 Main layers
- Routes: [routes/web.php](../routes/web.php)
- Controllers: [app/Http/Controllers](../app/Http/Controllers)
- Models: [app/Models](../app/Models)
- Views: [resources/views](../resources/views)
- Database schema: [database/migrations](../database/migrations)
- Seed data: [database/seeders](../database/seeders)

### 2.3 Request flow
1. User logs in via authentication middleware.
2. Laravel loads the route from [routes/web.php](../routes/web.php).
3. Corresponding controller handles request logic.
4. Controller reads/writes data through Eloquent models.
5. Views render results in Blade templates.
6. Role middleware decides whether user may access specific departments.

---

## 3. User roles and permissions

The system defines multiple roles. Roles are created in [database/seeders/DatabaseSeeder.php](../database/seeders/DatabaseSeeder.php) and seeded by [database/seeders/SampleDataSeeder.php](../database/seeders/SampleDataSeeder.php).

| Role | Purpose | Typical access |
| --- | --- | --- |
| super-admin | Full system administrator | Users, roles, access matrix, all departments |
| hospital-manager | Operational leadership | Dashboard, wards, reports, clinical zones, billing |
| receptionist | Front desk | Patients, appointments, registration |
| doctor | Clinical care | Visits, prescriptions, patient records, lab |
| nurse | IPD nursing care | Admissions, ward tasks, vitals, nursing notes |
| lab-technician | Diagnostics | Lab requests, results, processing |
| radiologist | Imaging | Radiology requests and reports |
| pharmacist | Pharmacy operations | Prescriptions, medication dispensing |
| cashier | Finance | Billing, payments, reconciliations |
| hr | Personnel operations | Employees, payroll |
| store-keeper | Inventory and procurement | Inventory, suppliers, purchase orders |
| patient | Patient portal | Own records, appointments, invoices, portal |

### Important access rule
- super-admin has full access.
- hospital-manager is operational leader and is allowed to view and manage monitoring dashboards and reporting, but cannot access sensitive user administration or full system controls.
- Each role is restricted using route middleware defined in [routes/web.php](../routes/web.php).

---

## 4. Default seeded users

The default users created during seeding are:

| Email | Role | Default password |
| --- | --- | --- |
| admin@hospital.test | super-admin | password |
| manager@hospital.test | hospital-manager | password |
| doctor@hospital.test | doctor | password |
| nurse@hospital.test | nurse | password |
| lab@hospital.test | lab-technician | password |
| radiology@hospital.test | radiologist | password |
| pharmacy@hospital.test | pharmacist | password |
| cashier@hospital.test | cashier | password |
| hr@hospital.test | hr | password |
| store@hospital.test | store-keeper | password |
| reception@hospital.test | receptionist | password |
| patient@hospital.test | patient | password |

> These defaults are defined in [database/seeders/SampleDataSeeder.php](../database/seeders/SampleDataSeeder.php).

---

## 5. Main departments and modules

### 5.1 Patient module
Responsible for registering and maintaining patient records.

Core files:
- [app/Http/Controllers/PatientController.php](../app/Http/Controllers/PatientController.php)
- [app/Models/Patient.php](../app/Models/Patient.php)

Main workflows:
- Register new patient
- Search patient by name or number
- View patient timeline and visit history
- Access patient portal if linked

### 5.2 Appointment module
Handles scheduled consultations and outpatient booking.

Core files:
- [app/Http/Controllers/AppointmentController.php](../app/Http/Controllers/AppointmentController.php)
- [app/Models/Appointment.php](../app/Models/Appointment.php)

Main workflows:
- Book appointment
- View queue
- Update status
- Reschedule or cancel visit

### 5.3 Consultation and visits
Doctors record consultations and patient complaints.

Core files:
- [app/Http/Controllers/VisitController.php](../app/Http/Controllers/VisitController.php)
- [app/Models/Visit.php](../app/Models/Visit.php)

Main workflows:
- Create visit record
- Save doctor diagnosis
- Capture vitals during or after patient assessment

### 5.4 Admissions and ward management
Tracks inpatient and ward management activities.

Core files:
- [app/Http/Controllers/AdmissionController.php](../app/Http/Controllers/AdmissionController.php)
- [app/Models/Admission.php](../app/Models/Admission.php)
- [app/Models/Ward.php](../app/Models/Ward.php)

Main workflows:
- Admit patient to ward
- Transfer patient between wards
- Record nursing notes
- Record vital signs
- Discharge patient

### 5.5 Laboratory module
Handles test requests, processing, and result review.

Core files:
- [app/Http/Controllers/LabController.php](../app/Http/Controllers/LabController.php)
- [app/Models/LabRequest.php](../app/Models/LabRequest.php)

Main workflows:
- Request lab investigation
- Record lab completion and result review
- Update sample status
- Review result history

### 5.6 Radiology module
Handles imaging requests and reports.

Core files:
- [app/Http/Controllers/RadiologyController.php](../app/Http/Controllers/RadiologyController.php)
- [app/Models/RadiologyRequest.php](../app/Models/RadiologyRequest.php)

Main workflows:
- Request x-ray or imaging study
- Assign/report result
- Download or review attachment

### 5.7 Pharmacy and prescriptions
Tracks medicine orders, dispensing, and stock movement.

Core files:
- [app/Http/Controllers/PharmacyController.php](../app/Http/Controllers/PharmacyController.php)
- [app/Http/Controllers/PrescriptionController.php](../app/Http/Controllers/PrescriptionController.php)
- [app/Models/Prescription.php](../app/Models/Prescription.php)

Main workflows:
- Prescribe medication
- Dispense medicine to patient
- Track stock usage and receipts

### 5.8 Billing and finance
Tracks invoice generation, payment receipt, insurance claims, and reconciliations.

Core files:
- [app/Http/Controllers/BillingController.php](../app/Http/Controllers/BillingController.php)
- [app/Http/Controllers/PaymentController.php](../app/Http/Controllers/PaymentController.php)
- [app/Http/Controllers/InsuranceClaimController.php](../app/Http/Controllers/InsuranceClaimController.php)
- [app/Models/Invoice.php](../app/Models/Invoice.php)

Main workflows:
- Generate or review invoice
- Record payment
- Submit insurance claim
- Reconcile cash collection

### 5.9 HR and staff management
Handles employee directories and payroll.

Core files:
- [app/Http/Controllers/HrController.php](../app/Http/Controllers/HrController.php)
- [app/Models/Employee.php](../app/Models/Employee.php)
- [app/Models/Payroll.php](../app/Models/Payroll.php)

### 5.10 Inventory and procurement
Tracks stock levels, supplier management, and purchase orders.

Core files:
- [app/Http/Controllers/InventoryController.php](../app/Http/Controllers/InventoryController.php)
- [app/Models/InventoryItem.php](../app/Models/InventoryItem.php)
- [app/Models/Supplier.php](../app/Models/Supplier.php)

### 5.11 Reports and dashboards
Leadership and department dashboards are built around reports and summary panels.

Core files:
- [app/Http/Controllers/DashboardController.php](../app/Http/Controllers/DashboardController.php)
- [app/Http/Controllers/ReportController.php](../app/Http/Controllers/ReportController.php)
- [resources/views/dashboard.blade.php](../resources/views/dashboard.blade.php)
- [resources/views/reports/index.blade.php](../resources/views/reports/index.blade.php)

Main outputs:
- Clinical zone overview
- Capacity by clinical zone
- Mortality overview
- Recent admissions watchlist
- Operational brief
- Executive summary
- Department trend overview

---

## 6. Database design overview

The schema is maintained under [database/migrations](../database/migrations) and follows a hospital operational model.

### Core tables
- users
- roles
- role_has_permissions
- permissions
- patients
- appointments
- visits
- admissions
- wards
- beds
- invoices
- payments
- insurance_claims
- lab_requests
- radiology_requests
- prescriptions
- inventory_items
- inventory_movements
- suppliers
- purchase_orders
- purchase_order_items
- employees
- payroll
- notifications
- patient_portal_accounts
- audit_logs
- death_records
- backup_logs

### Example key relationships
- patients -> appointments
- patients -> visits
- visits -> prescriptions
- visits -> lab_requests
- visits -> radiology_requests
- patients -> admissions
- wards -> admissions
- patients -> invoices
- invoices -> payments
- patients -> insurance_claims
- users -> notifications
- users -> audit_logs

### Clinical zones supported
The system includes clinical zone categorization for wards such as:
- ICU
- Labour & Maternity
- Paediatrics
- General wards and specialty departments

This is important for dashboard summaries and leadership reporting.

---

## 7. Data flow in the hospital system

### 7.1 Patient journey
1. A patient is registered by receptionist or admin.
2. Appointment is booked.
3. Patient attends consultation/visit.
4. Doctor records diagnosis and complaints.
5. Lab or radiology request may be created.
6. Prescription may be generated.
7. Patient may be admitted to a ward.
8. Billing is generated according to services used.
9. Payment or insurance claim is processed.
10. Reports and dashboard metrics are updated.

### 7.2 Clinical data flow
- patient -> visit -> diagnosis -> prescription/lab/radiology -> admission -> nursing notes -> discharge

### 7.3 Finance data flow
- patient -> invoice -> payment
- patient -> insurance claim -> review -> settlement

### 7.4 Inventory data flow
- suppliers -> purchase orders -> inventory items -> stock movements -> usage in pharmacy or operations

### 7.5 Reporting data flow
- operational records are aggregated from admissions, wards, deaths, payments, lab requests, and prescriptions
- leadership dashboards read this data and summarize trends by department and clinical zone

---

## 8. How to use the system

### 8.1 Initial setup
1. Start XAMPP.
2. Start Apache and MySQL.
3. Ensure the project exists in the XAMPP htdocs folder.
4. Create or confirm database `hospital` exists.
5. Run migrations and seeders:

```bash
php artisan migrate:fresh --seed
```

### 8.2 Login
Open the app in browser:
- http://localhost/hospital/public
- or http://127.0.0.1:8000 if running via artisan serve

Login with one of the seeded accounts from the table above.

### 8.3 Common user workflows

#### Super-admin
- Manage user accounts and roles
- View access matrix
- Monitor all major departments
- Manage all operational dashboards

#### Hospital Manager
- Review dashboard and capacity by zone
- Monitor mortality and operational pressure
- Review high-risk wards and leadership alerts
- Open reports and strategic summaries

#### Receptionist
- Register new patients
- Book appointments
- Manage front desk workflow

#### Doctor
- View patient records
- Create visit and diagnosis records
- Request lab and radiology investigations
- Write prescriptions

#### Nurse
- Admit and discharge patients
- Record vital signs
- Add nursing notes
- Monitor ward occupancy and patient care status

#### Cashier
- Create and review invoices
- Record payment transactions
- Process claims and reconciliations

#### Lab Technician
- Process lab requests
- Update sample status
- Review completed results

#### Pharmacist
- View prescriptions
- Dispense medication
- Review pharmacy inventory and stock movement

#### Store Keeper
- Manage inventory items and stock levels
- Approve orders and track movement records

#### HR Officer
- Maintain employee records
- Monitor payroll and staff status

#### Patient
- Log into patient portal
- View personal appointments and invoices
- Check portal-related notifications and records

---

## 9. Dashboard and reporting guide

### Dashboard modules
The dashboard is driven by [app/Http/Controllers/DashboardController.php](../app/Http/Controllers/DashboardController.php).

It can show:
- Overview stats
- Operational brief
- Clinical zone overview
- Mortality overview
- High-risk units
- Recent admissions watchlist
- Performance scorecard
- Executive board summary

### Report page
The reports page is driven by [app/Http/Controllers/ReportController.php](../app/Http/Controllers/ReportController.php).

It includes:
- Revenue summary
- Lab turnaround time summary
- Clinical zone summary
- Capacity by clinical zone
- Death overview and cause breakdown

---

## 10. Security and access model

### Role-based protection
Routes are protected with middleware in [routes/web.php](../routes/web.php).

Examples:
- super-admin only: users, access matrix, audit logs
- hospital-manager: dashboard, reports, wards, clinical zones
- doctor: visits and consult workflows
- cashier: billing and payment routes
- nurse: admissions and nursing routes

### Audit trail
Audit logging is maintained in [app/Models/AuditLog.php](../app/Models/AuditLog.php) and is used to trace major operations.

### Good practice
- Do not grant broad access to non-admin users.
- Always test route access after adding a new role.
- Keep database migrations clean and idempotent.

---

## 11. Troubleshooting

### Problem: browser says connection refused
Cause:
- MySQL or Apache not started
- Database missing
- XAMPP not running

Solution:
- Start MySQL and Apache in XAMPP
- Check database `hospital` exists
- Run `php artisan migrate:fresh --seed`

### Problem: 500 Internal Server Error
Cause:
- Database connection issue
- Missing migration table
- Database credentials mismatch

Solution:
- Check `.env` values
- Validate MySQL is running
- Run `php artisan config:clear`
- Run `php artisan migrate`

### Problem: patient or role cannot access page
Cause:
- Missing role assignment
- Wrong middleware entry
- Database seed not run

Solution:
- Confirm role is assigned
- Verify route middleware in [routes/web.php](../routes/web.php)
- Re-run seeding

---

## 12. Recommended future improvements

- Add role-based permission matrix documentation to UI
- Add patient portal access dashboards for each patient
- Add export to PDF/CSV for all department reports
- Add notification center for department-specific alerts
- Add system health and uptime monitoring dashboard
- Add branch and facility support for multi-site hospitals

---

## 13. Summary

This system is designed to serve a modern hospital environment with separated responsibilities across clinical, financial, operational, and administrative functions. The hospital management flow is centered on patient care, bed capacity, admission, billing, reporting, and leadership monitoring. Every user role is intentionally restricted so users see only the functions relevant to their department.

The application is operationally ready once the environment is correctly started and the MySQL database is available. The role-based design and dashboard logic are already built to support normal hospital workflows.

---

## 14. Useful project references

- [routes/web.php](../routes/web.php)
- [app/Http/Controllers/DashboardController.php](../app/Http/Controllers/DashboardController.php)
- [app/Http/Controllers/ReportController.php](../app/Http/Controllers/ReportController.php)
- [database/seeders/DatabaseSeeder.php](../database/seeders/DatabaseSeeder.php)
- [database/seeders/SampleDataSeeder.php](../database/seeders/SampleDataSeeder.php)
- [docs/hms-phase-5-erd.md](./hms-phase-5-erd.md)
- [docs/deployment-security.md](./deployment-security.md)
- [README.md](../README.md)


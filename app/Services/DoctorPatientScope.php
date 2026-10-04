<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\Patient;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

class DoctorPatientScope
{
    public function patientsFor(User $user): Builder
    {
        return Patient::query()->when($user->hasRole('doctor'), function (Builder $query) use ($user): void {
            $query->whereHas('appointments', fn (Builder $appointmentQuery) => $appointmentQuery
                ->where('doctor_id', $user->id)
                ->whereNotIn('status', ['cancelled', 'no_show']));
        });
    }

    public function allows(User $user, int $patientId): bool
    {
        if (! $user->hasRole('doctor')) {
            return true;
        }

        return Appointment::where('doctor_id', $user->id)
            ->where('patient_id', $patientId)
            ->whereNotIn('status', ['cancelled', 'no_show'])
            ->exists();
    }
}

<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class DailyNumberService
{
    public function next(string $scope, string $prefix): string
    {
        $date = now()->format('Ymd');

        return DB::transaction(function () use ($scope, $prefix, $date): string {
            DB::table('daily_number_sequences')->insertOrIgnore([
                'scope' => $scope,
                'sequence_date' => $date,
                'next_value' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $sequence = DB::table('daily_number_sequences')
                ->where('scope', $scope)
                ->where('sequence_date', $date)
                ->lockForUpdate()
                ->first();

            $value = (int) $sequence->next_value;
            DB::table('daily_number_sequences')
                ->where('id', $sequence->id)
                ->update(['next_value' => $value + 1, 'updated_at' => now()]);

            return sprintf('%s-%s-%04d', $prefix, $date, $value);
        });
    }
}

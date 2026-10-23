<?php

namespace Database\Seeders;

use App\Models\PublicHoliday;
use Illuminate\Database\Seeder;

/**
 * Seeds ONLY fixed-date Philippine national holidays.
 * Movable / proclaimed holidays are left for admin entry.
 */
class PublicHolidaySeeder extends Seeder
{
    public function run(): void
    {
        $years = range((int) now('Asia/Manila')->year - 1, (int) now('Asia/Manila')->year + 2);

        $fixed = [
            ['01-01', "New Year's Day"],
            ['04-09', 'Araw ng Kagitingan (Day of Valor)'],
            ['05-01', 'Labor Day'],
            ['06-12', 'Independence Day'],
            ['08-21', 'Ninoy Aquino Day'],
            ['11-01', "All Saints' Day"],
            ['11-30', 'Bonifacio Day'],
            ['12-25', 'Christmas Day'],
            ['12-30', 'Rizal Day'],
            // Common fixed observances often treated as regular holidays/special:
            ['12-08', 'Feast of the Immaculate Conception'],
            ['12-31', "New Year's Eve"],
        ];

        foreach ($years as $year) {
            foreach ($fixed as [$md, $name]) {
                $date = sprintf('%04d-%s', $year, $md);
                $existing = PublicHoliday::query()
                    ->whereDate('date', $date)
                    ->where('name', $name)
                    ->first();
                if ($existing) {
                    $existing->forceFill([
                        'type' => 'national',
                        'scope' => null,
                    ])->save();
                    continue;
                }

                PublicHoliday::query()->create([
                    'date' => $date,
                    'name' => $name,
                    'type' => 'national',
                    'scope' => null,
                ]);
            }
        }
    }
}

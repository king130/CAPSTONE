<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $seeders = [
            RbacSeeder::class,
            ContractTypeSeeder::class,
            SkillCatalogSeeder::class,
        ];

        // Demo accounts (including Municipal Hall demo) are local/testing only.
        if (app()->environment(['local', 'testing'])) {
            $seeders[] = DemoAccountsSeeder::class;
            $seeders[] = CaviteDirectorySeeder::class;
            $seeders[] = PublicHolidaySeeder::class;
        }

        $this->call($seeders);
    }
}

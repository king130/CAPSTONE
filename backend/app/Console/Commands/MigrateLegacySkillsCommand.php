<?php

namespace App\Console\Commands;

use App\Services\SkillCatalogService;
use Illuminate\Console\Command;

class MigrateLegacySkillsCommand extends Command
{
    protected $signature = 'skills:migrate-legacy';

    protected $description = 'Match free-text internship skills to the catalog and keep leftovers as pending customs';

    public function handle(SkillCatalogService $skills): int
    {
        $this->info('Migrating legacy free-text skills…');
        $stats = $skills->migrateLegacyFreeText(fn (string $line) => $this->line($line));
        $this->info(sprintf(
            'Done. Internships=%d matched=%d pending=%d',
            $stats['internships'],
            $stats['matched'],
            $stats['created_pending']
        ));

        return self::SUCCESS;
    }
}

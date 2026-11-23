<?php

namespace App\Console\Commands;

use App\Models\Visit;
use Illuminate\Console\Command;

/** Deletes page views older than 13 months: the visitor figures never look further back. */
class PruneVisits extends Command
{
    protected $signature = 'visits:prune';

    protected $description = 'Delete visitor records older than 13 months';

    public function handle(): int
    {
        $deleted = 0;
        do {
            $batch = Visit::where('visited_on', '<', now()->subMonths(13)->toDateString())->limit(5000)->delete();
            $deleted += $batch;
        } while ($batch > 0);
        $this->info("Deleted $deleted old visits.");

        return self::SUCCESS;
    }
}

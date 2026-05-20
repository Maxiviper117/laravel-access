<?php

declare(strict_types=1);

namespace Maxiviper117\Access\Commands;

use Illuminate\Console\Command;
use Maxiviper117\Access\Support\AccessCache;

class ClearAccessCommand extends Command
{
    protected $signature = 'access:clear';

    protected $description = 'Clear Laravel Access permission cache.';

    public function handle(AccessCache $cache): int
    {
        $cache->clear();
        $this->info('Access cache cleared.');

        return self::SUCCESS;
    }
}

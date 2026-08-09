<?php

declare(strict_types=1);

namespace Onelegstudios\Refit\Console\Commands;

use Illuminate\Console\Command;

class RefitCommand extends Command
{
    /**
     * The command signature.
     */
    protected $signature = 'refit:placeholder';

    /**
     * The command description.
     */
    protected $description = 'Placeholder Artisan command shipped by the package refit.';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->line('Refit placeholder command executed.');

        return self::SUCCESS;
    }
}

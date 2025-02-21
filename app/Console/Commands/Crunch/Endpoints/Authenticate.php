<?php

namespace App\Console\Commands\Crunch\Endpoints;

use App\Services\Crunch;
use Exception;
use Illuminate\Console\Command;

class Authenticate extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'crunch:authenticate';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Test crunch API Auth';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle(): void
    {
        try {
            // Instantiate the PNCP service
            $crunchService = new Crunch();

            // Call the service method to create the contract certificate bundle
           $crunchService->authenticate();
        } catch (Exception $e) {
            info('Error during certificate revocation: ' . $e->getMessage());
        }
    }
}

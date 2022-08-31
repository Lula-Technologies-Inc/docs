<?php

namespace App\Console\Commands;

use App\Services\LulaSafeService;
use Illuminate\Console\Command;

class RunService extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'command:run';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle(LulaSafeService $apiService)
    {
        $apiService->run();

        dd('Finish');
    }
}

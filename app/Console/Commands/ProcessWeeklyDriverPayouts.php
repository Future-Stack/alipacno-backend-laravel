<?php

namespace App\Console\Commands;

use App\Services\DriverPayoutService;
use Illuminate\Console\Command;

class ProcessWeeklyDriverPayouts extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'drivers:process-weekly-payouts';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Process net weekly driver payouts for the previous week (Week N - 1-week payment lag) via Stripe Connect';

    /**
     * Execute the console command.
     */
    public function handle(DriverPayoutService $payoutService)
    {
        $this->info('Starting weekly driver payout processing for previous week...');
        $payouts = $payoutService->processLaggedWeeklyPayouts();

        $this->info('Processed ' . count($payouts) . ' driver weekly payout records.');
        return Command::SUCCESS;
    }
}

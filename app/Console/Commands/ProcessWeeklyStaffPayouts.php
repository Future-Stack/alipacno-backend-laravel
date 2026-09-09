<?php

namespace App\Console\Commands;

use App\Services\StaffPayoutService;
use Illuminate\Console\Command;

class ProcessWeeklyStaffPayouts extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'staff:process-weekly-payouts';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Process weekly staff salary payouts for the previous week (Week N - 1-week payment lag) via Stripe Connect';

    /**
     * Execute the console command.
     */
    public function handle(StaffPayoutService $payoutService)
    {
        $this->info('Starting weekly staff payout processing for previous week...');
        $payouts = $payoutService->processLaggedWeeklyPayouts();

        $this->info('Processed ' . count($payouts) . ' staff weekly payout records.');
        return Command::SUCCESS;
    }
}

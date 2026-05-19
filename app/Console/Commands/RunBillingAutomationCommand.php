<?php

namespace App\Console\Commands;

use App\Services\Billing\BillingAutomationRunner;
use Illuminate\Console\Command;

class RunBillingAutomationCommand extends Command
{
    protected $signature = 'billing:run-automation {--force : Run regardless of configured time}';

    protected $description = 'Create renewal orders and send unpaid order reminders';

    public function handle(BillingAutomationRunner $runner): int
    {
        $result = $runner->run(force: (bool) $this->option('force'));

        if ($result['skipped_time']) {
            $this->info('Skipped: not the configured run time (use --force to run anyway).');

            return self::SUCCESS;
        }

        $this->info("Orders created: {$result['orders_created']}");
        $this->info("Reminders sent: {$result['reminders_sent']}");

        return self::SUCCESS;
    }
}

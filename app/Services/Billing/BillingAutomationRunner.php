<?php

namespace App\Services\Billing;

use App\Enums\DomainStatus;
use App\Enums\OrderStatus;
use App\Enums\ServiceStatus;
use App\Models\BillingAutomationLog;
use App\Models\BillingAutomationSetting;
use App\Models\Domain;
use App\Models\EmailTemplate;
use App\Models\Order;
use App\Models\Service;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class BillingAutomationRunner
{
    public function __construct(
        private readonly RenewalOrderFactory $orderFactory,
        private readonly BillingEmailSender $emailSender,
    ) {}

    /**
     * @return array{orders_created: int, reminders_sent: int, skipped_time: bool}
     */
    public function run(?CarbonInterface $at = null, bool $force = false): array
    {
        $at = Carbon::instance($at ?? now());
        $settings = BillingAutomationSetting::instance();

        if (! $force && ! $settings->shouldRunAt($at)) {
            return ['orders_created' => 0, 'reminders_sent' => 0, 'skipped_time' => true];
        }

        if (! $settings->is_enabled) {
            return ['orders_created' => 0, 'reminders_sent' => 0, 'skipped_time' => false];
        }

        $ordersCreated = 0;
        $ordersCreated += $this->createServiceRenewalOrders($settings, $at);
        $ordersCreated += $this->createStandaloneDomainOrders($settings, $at);
        $remindersSent = $this->sendUnpaidOrderReminders($settings, $at);

        return [
            'orders_created' => $ordersCreated,
            'reminders_sent' => $remindersSent,
            'skipped_time' => false,
        ];
    }

    private function createServiceRenewalOrders(BillingAutomationSetting $settings, CarbonInterface $at): int
    {
        $created = 0;
        $today = Carbon::instance($at)->startOfDay();

        Service::query()
            ->where('status', ServiceStatus::Active)
            ->whereNotNull('due_at')
            ->whereDate('due_at', '<=', $today->copy()->addDays($settings->service_order_days_before))
            ->whereDate('due_at', '>=', $today->copy()->subDays($settings->service_order_days_before))
            ->with(['client', 'product', 'domain'])
            ->orderBy('id')
            ->chunkById(100, function (Collection $services) use ($settings, $today, &$created) {
                foreach ($services as $service) {
                    $renewalDueDate = Carbon::instance($service->due_at)->startOfDay();

                    if ($this->renewalOrderCreateDateNotReached($renewalDueDate, $settings->service_order_days_before, $today)) {
                        continue;
                    }

                    $order = $this->orderFactory->createForService($service, $renewalDueDate);
                    if ($order === null) {
                        continue;
                    }

                    $created++;
                    $this->sendOrderCreatedEmail($settings, $order);
                }
            });

        return $created;
    }

    private function createStandaloneDomainOrders(BillingAutomationSetting $settings, CarbonInterface $at): int
    {
        $created = 0;
        $today = Carbon::instance($at)->startOfDay();

        Domain::query()
            ->whereIn('status', [DomainStatus::Active, DomainStatus::Expired])
            ->where('auto_renew', true)
            ->whereDoesntHave('services', fn ($q) => $q->where('status', ServiceStatus::Active))
            ->whereNotNull('due_at')
            ->whereDate('due_at', '<=', $today->copy()->addDays($settings->domain_order_days_before))
            ->whereDate('due_at', '>=', $today->copy()->subDays($settings->domain_order_days_before))
            ->with('client')
            ->orderBy('id')
            ->chunkById(100, function (Collection $domains) use ($settings, $today, &$created) {
                foreach ($domains as $domain) {
                    $renewalDueDate = Carbon::instance($domain->due_at)->startOfDay();

                    if ($this->renewalOrderCreateDateNotReached($renewalDueDate, $settings->domain_order_days_before, $today)) {
                        continue;
                    }

                    $order = $this->orderFactory->createForDomain($domain, $renewalDueDate);
                    if ($order === null) {
                        continue;
                    }

                    $created++;
                    $this->sendOrderCreatedEmail($settings, $order);
                }
            });

        return $created;
    }

    /**
     * True when the configured lead time has not elapsed yet (order must not be created).
     */
    private function renewalOrderCreateDateNotReached(
        CarbonInterface $renewalDueDate,
        int $daysBefore,
        CarbonInterface $at,
    ): bool {
        $createOn = Carbon::instance($renewalDueDate)->startOfDay()->subDays($daysBefore);

        return $createOn->isAfter(Carbon::instance($at)->startOfDay());
    }

    private function sendUnpaidOrderReminders(BillingAutomationSetting $settings, CarbonInterface $at): int
    {
        if (! $settings->send_reminder_emails) {
            return 0;
        }

        $sent = 0;
        $sent += $this->sendRemindersForOffset($settings, $at, $settings->reminder_days_before_first, 'reminder_before_first');
        $sent += $this->sendRemindersForOffset($settings, $at, $settings->reminder_days_before_due, 'reminder_before_due');
        $sent += $this->sendRemindersForOverdue($settings, $at, $settings->reminder_days_after_due, 'reminder_after_due');

        return $sent;
    }

    private function sendRemindersForOffset(
        BillingAutomationSetting $settings,
        CarbonInterface $at,
        int $daysBeforeDue,
        string $event,
    ): int {
        $orderDueDate = Carbon::instance($at)->startOfDay()->addDays($daysBeforeDue);
        $sent = 0;

        Order::query()
            ->where('status', OrderStatus::Unpaid)
            ->whereNotNull('due_at')
            ->whereDate('due_at', $orderDueDate->toDateString())
            ->with('client')
            ->orderBy('id')
            ->chunkById(100, function (Collection $orders) use ($orderDueDate, $event, &$sent) {
                foreach ($orders as $order) {
                    if ($this->sendReminderEmail($order, $orderDueDate, $event)) {
                        $sent++;
                    }
                }
            });

        return $sent;
    }

    private function sendRemindersForOverdue(
        BillingAutomationSetting $settings,
        CarbonInterface $at,
        int $daysAfterDue,
        string $event,
    ): int {
        $orderDueDate = Carbon::instance($at)->startOfDay()->subDays($daysAfterDue);
        $sent = 0;

        Order::query()
            ->where('status', OrderStatus::Unpaid)
            ->whereNotNull('due_at')
            ->whereDate('due_at', $orderDueDate->toDateString())
            ->with('client')
            ->orderBy('id')
            ->chunkById(100, function (Collection $orders) use ($orderDueDate, $event, &$sent) {
                foreach ($orders as $order) {
                    if ($this->sendReminderEmail($order, $orderDueDate, $event)) {
                        $sent++;
                    }
                }
            });

        return $sent;
    }

    private function sendOrderCreatedEmail(BillingAutomationSetting $settings, Order $order): void
    {
        if (! $settings->send_order_created_email) {
            return;
        }

        $order->loadMissing('client');
        $email = $order->client?->email;
        if ($email === null || $email === '') {
            return;
        }

        $template = EmailTemplate::query()
            ->where('slug', 'order_created')
            ->where('is_active', true)
            ->first();

        if ($template === null) {
            return;
        }

        $this->emailSender->sendToClient(
            $template,
            $email,
            $order->emailVariables(),
            $order->client_id,
            orderId: $order->id,
        );
    }

    private function sendReminderEmail(Order $order, CarbonInterface $contextDate, string $event): bool
    {
        if (BillingAutomationLog::wasHandled(
            BillingAutomationLog::SUBJECT_ORDER,
            $order->id,
            $event,
            $contextDate,
        )) {
            return false;
        }

        $order->loadMissing('client');
        $email = $order->client?->email;
        if ($email === null || $email === '') {
            return false;
        }

        $template = EmailTemplate::query()
            ->where('slug', 'order_payment_reminder')
            ->where('is_active', true)
            ->first();

        if ($template === null) {
            return false;
        }

        $this->emailSender->sendToClient(
            $template,
            $email,
            $order->emailVariables(),
            $order->client_id,
            orderId: $order->id,
        );

        BillingAutomationLog::record(
            BillingAutomationLog::SUBJECT_ORDER,
            $order->id,
            $event,
            $contextDate,
            $order->id,
        );

        return true;
    }
}

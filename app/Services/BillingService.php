<?php

namespace App\Services;

use App\Models\BillingCycle;
use App\Models\BillingPlan;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Subscription;
use App\Models\SubscriptionItem;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class BillingService
{
    public function __construct(private InvoiceService $invoiceService) {}

    private function nextPeriodEnd(Carbon $from, string $interval): Carbon
    {
        return match ($interval) {
            'monthly'   => $from->copy()->addMonth(),
            'quarterly' => $from->copy()->addMonths(3),
            'yearly'    => $from->copy()->addYear(),
        };
    }

    public function subscribe(Customer $customer, BillingPlan $plan): Subscription
    {
        return DB::transaction(function () use ($customer, $plan) {
            $start    = today();
            $trialEnd = $plan->trial_days > 0 ? $start->copy()->addDays($plan->trial_days) : null;
            $periodEnd = $trialEnd ?? $this->nextPeriodEnd($start, $plan->interval);

            $subscription = Subscription::create([
                'organization_id'      => $customer->organization_id,
                'customer_id'          => $customer->id,
                'plan_id'              => $plan->id,
                'status'               => $trialEnd ? 'trialing' : 'active',
                'current_period_start' => $start,
                'current_period_end'   => $periodEnd,
                'trial_end'            => $trialEnd,
            ]);

            SubscriptionItem::create([
                'subscription_id' => $subscription->id,
                'description'     => $plan->name,
                'qty'             => 1,
                'unit_price'      => $plan->price,
            ]);

            return $subscription;
        });
    }

    public function generateInvoice(Subscription $subscription): Invoice
    {
        return DB::transaction(function () use ($subscription) {
            $amount  = $subscription->items->sum(fn($i) => $i->qty * $i->unit_price);
            $adminId = $subscription->organization->users()->role('super_admin')->value('id');

            $invoice = Invoice::create([
                'organization_id' => $subscription->organization_id,
                'customer_id'     => $subscription->customer_id,
                'created_by'      => $adminId,
                'invoice_no'      => $this->invoiceService->generateNumber($subscription->organization_id),
                'status'          => 'sent',
                'issue_date'      => today(),
                'due_date'        => today()->addDays(14),
                'tax_rate'        => 11.00,
                'tax_mechanism'   => 'regular',
                'sent_at'         => now(),
            ]);

            foreach ($subscription->items as $item) {
                $invoice->items()->create([
                    'item_id'     => $item->item_id,
                    'description' => $item->description . ' — ' . $subscription->current_period_start->format('M Y'),
                    'qty'         => $item->qty,
                    'unit_price'  => $item->unit_price,
                    'line_total'  => $item->qty * $item->unit_price,
                ]);
            }

            $this->invoiceService->recalculate($invoice);

            BillingCycle::create([
                'subscription_id' => $subscription->id,
                'invoice_id'      => $invoice->id,
                'period_start'    => $subscription->current_period_start,
                'period_end'      => $subscription->current_period_end,
                'status'          => 'invoiced',
                'amount'          => $amount,
            ]);

            $newStart = $subscription->current_period_end->copy()->addDay();
            $subscription->update([
                'current_period_start' => $newStart,
                'current_period_end'   => $this->nextPeriodEnd($newStart, $subscription->plan->interval),
                'status'               => 'active',
            ]);

            return $invoice;
        });
    }

    public function cancel(Subscription $subscription, string $reason = ''): void
    {
        $subscription->update(['status' => 'cancelled', 'cancelled_at' => now(), 'cancel_reason' => $reason]);
    }
}

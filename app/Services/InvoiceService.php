<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Shoot;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class InvoiceService
{
    /**
     * Generate invoices for the provided billing period.
     */
    public function generateForPeriod(Carbon $start, Carbon $end): Collection
    {
        $start = $start->copy()->startOfDay();
        $end = $end->copy()->endOfDay();

        $shoots = Shoot::with([
                'payments' => function ($query) {
                    $query->where('status', Payment::STATUS_COMPLETED);
                },
                'photographer',
            ])
            ->whereBetween('scheduled_date', [$start->toDateString(), $end->toDateString()])
            ->whereNotNull('photographer_id')
            ->get();

        if ($shoots->isEmpty()) {
            return collect();
        }

        $grouped = $shoots->groupBy('photographer_id');

        return DB::transaction(function () use ($grouped, $start, $end) {
            $invoices = collect();

            foreach ($grouped as $photographerId => $photographerShoots) {
                $totalAmount = $photographerShoots->sum(fn (Shoot $shoot) => (float) $shoot->total_quote);
                $amountPaid = $photographerShoots
                    ->flatMap(fn (Shoot $shoot) => $shoot->payments)
                    ->sum(fn ($payment) => (float) $payment->amount);

                $invoice = Invoice::updateOrCreate(
                    [
                        'photographer_id' => $photographerId,
                        'billing_period_start' => $start->toDateString(),
                        'billing_period_end' => $end->toDateString(),
                    ],
                    [
                        'total_amount' => $totalAmount,
                        'amount_paid' => $amountPaid,
                        'is_paid' => $totalAmount > 0 ? $amountPaid >= $totalAmount : false,
                    ]
                );

                $invoice->shoots()->sync($photographerShoots->pluck('id')->all());

                $invoices->push($invoice->fresh(['photographer', 'shoots.payments']));
            }

            return $invoices;
        });
    }

    /**
     * Generate invoices for the last completed calendar week.
     */
    public function generateForLastCompletedWeek(): Collection
    {
        $end = now()->startOfWeek()->subDay()->endOfDay();
        $start = $end->copy()->startOfWeek();

        return $this->generateForPeriod($start, $end);
    }
}

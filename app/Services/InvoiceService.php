<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Payment;
use App\Models\Shoot;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class InvoiceService
{
    public function generateForClient(User $client, Carbon $periodStart, Carbon $periodEnd): Invoice
    {
        return $this->generateInvoice($client, Invoice::ROLE_CLIENT, $periodStart, $periodEnd);
    }

    public function generateForPhotographer(User $photographer, Carbon $periodStart, Carbon $periodEnd): Invoice
    {
        return $this->generateInvoice($photographer, Invoice::ROLE_PHOTOGRAPHER, $periodStart, $periodEnd);
    }

    public function generateInvoice(User $user, string $role, Carbon $periodStart, Carbon $periodEnd): Invoice
    {
        $role = strtolower($role);

        if (!in_array($role, [Invoice::ROLE_CLIENT, Invoice::ROLE_PHOTOGRAPHER], true)) {
            throw new InvalidArgumentException('Invalid invoice role provided.');
        }

        $startDate = $periodStart->copy()->startOfDay();
        $endDate = $periodEnd->copy()->endOfDay();

        return DB::transaction(function () use ($user, $role, $startDate, $endDate) {
            $invoice = Invoice::create([
                'user_id' => $user->id,
                'role' => $role,
                'period_start' => $startDate->toDateString(),
                'period_end' => $endDate->toDateString(),
                'status' => Invoice::STATUS_DRAFT,
            ]);

            $shootsQuery = Shoot::with(['service', 'payments' => function ($query) use ($startDate, $endDate) {
                $query->where('status', Payment::STATUS_COMPLETED)
                    ->whereBetween('processed_at', [$startDate, $endDate]);
            }])
                ->whereBetween('scheduled_date', [$startDate->toDateString(), $endDate->toDateString()])
                ->whereNotNull('scheduled_date')
                ->orderBy('scheduled_date');

            if ($role === Invoice::ROLE_CLIENT) {
                $shootsQuery->where('client_id', $user->id);
            } else {
                $shootsQuery->where('photographer_id', $user->id);
            }

            $completedStatuses = [
                Shoot::WORKFLOW_COMPLETED,
                Shoot::WORKFLOW_ADMIN_VERIFIED,
            ];

            $shoots = $shootsQuery
                ->whereIn('workflow_status', $completedStatuses)
                ->get();

            foreach ($shoots as $shoot) {
                $serviceName = optional($shoot->service)->name;

                $amount = $role === Invoice::ROLE_PHOTOGRAPHER
                    ? ($shoot->base_quote ?? $shoot->total_quote ?? 0)
                    : ($shoot->total_quote ?? $shoot->base_quote ?? 0);

                $invoice->items()->create([
                    'shoot_id' => $shoot->id,
                    'type' => InvoiceItem::TYPE_CHARGE,
                    'description' => trim(sprintf('Shoot #%d%s', $shoot->id, $serviceName ? " - {$serviceName}" : '')),
                    'quantity' => 1,
                    'unit_amount' => $amount,
                    'total_amount' => $amount,
                    'recorded_at' => $shoot->scheduled_date,
                    'meta' => [
                        'workflow_status' => $shoot->workflow_status,
                    ],
                ]);

                if ($role === Invoice::ROLE_CLIENT) {
                    foreach ($shoot->payments as $payment) {
                        $invoice->items()->create([
                            'shoot_id' => $shoot->id,
                            'type' => InvoiceItem::TYPE_PAYMENT,
                            'description' => sprintf('Payment %s', $payment->square_payment_id),
                            'quantity' => 1,
                            'unit_amount' => $payment->amount,
                            'total_amount' => $payment->amount,
                            'recorded_at' => $payment->processed_at,
                            'meta' => [
                                'payment_id' => $payment->id,
                            ],
                        ]);
                    }
                }
            }

            $invoice->refreshTotals();

            return $invoice->fresh(['items']);
        });
    }
}

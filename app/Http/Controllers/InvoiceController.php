<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Models\Payment;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class InvoiceController extends Controller
{
    public function index(Request $request)
    {
        $query = Invoice::with(['photographer', 'salesRep'])->withCount('shoots');

        if ($request->filled('photographer_id')) {
            $query->where('photographer_id', $request->input('photographer_id'));
        }

        if ($request->has('paid')) {
            $query->where('is_paid', filter_var($request->input('paid'), FILTER_VALIDATE_BOOLEAN));
        }

        if ($request->filled('start')) {
            $start = Carbon::parse($request->input('start'))->startOfDay();
            $query->whereDate('billing_period_start', '>=', $start);
        }

        if ($request->filled('end')) {
            $end = Carbon::parse($request->input('end'))->endOfDay();
            $query->whereDate('billing_period_end', '<=', $end);
        }

        $invoices = $query
            ->orderByDesc('billing_period_start')
            ->paginate($request->integer('per_page', 15));

        return response()->json($invoices);
    }

    public function download(Invoice $invoice): StreamedResponse
    {
        $invoice->loadMissing(['photographer', 'salesRep', 'shoots.client', 'shoots.payments']);

        $filename = sprintf(
            'invoice-%s-%s-%s.csv',
            $invoice->photographer?->username ?? 'photographer',
            $invoice->billing_period_start->format('Ymd'),
            $invoice->billing_period_end->format('Ymd')
        );

        return response()->streamDownload(function () use ($invoice) {
            $handle = fopen('php://output', 'w');

            fputcsv($handle, ['Invoice ID', $invoice->id]);
            fputcsv($handle, ['Photographer', optional($invoice->photographer)->name]);
            fputcsv($handle, ['Billing Period', $invoice->billing_period_start->toDateString() . ' - ' . $invoice->billing_period_end->toDateString()]);
            fputcsv($handle, []);
            fputcsv($handle, ['Shoot ID', 'Scheduled Date', 'Client', 'Total Quote', 'Payments Received']);

            foreach ($invoice->shoots as $shoot) {
                $paymentsReceived = $shoot->payments
                    ->where('status', Payment::STATUS_COMPLETED)
                    ->sum('amount');

                fputcsv($handle, [
                    $shoot->id,
                    optional($shoot->scheduled_date)->toDateString(),
                    optional($shoot->client)->name,
                    number_format((float) $shoot->total_quote, 2, '.', ''),
                    number_format((float) $paymentsReceived, 2, '.', ''),
                ]);
            }

            fputcsv($handle, []);
            fputcsv($handle, ['Total', number_format((float) $invoice->total_amount, 2, '.', '')]);
            fputcsv($handle, ['Amount Paid', number_format((float) $invoice->amount_paid, 2, '.', '')]);
            fputcsv($handle, ['Paid', $invoice->is_paid ? 'Yes' : 'No']);

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv',
        ]);
    }

    public function markPaid(Request $request, Invoice $invoice)
    {
        $data = $request->validate([
            'paid_at' => ['nullable', 'date'],
            'amount_paid' => ['nullable', 'numeric', 'min:0'],
            'is_sent' => ['nullable', 'boolean'],
        ]);

        $invoice->fill([
            'is_paid' => true,
            'amount_paid' => $data['amount_paid'] ?? $invoice->total_amount,
            'paid_at' => isset($data['paid_at']) ? Carbon::parse($data['paid_at']) : now(),
        ]);

        if (array_key_exists('is_sent', $data)) {
            $invoice->is_sent = $data['is_sent'];
        }

        $invoice->save();

        return response()->json([
            'data' => $invoice->fresh(['photographer', 'salesRep'])->loadCount('shoots'),
        ]);
    }
}

<?php

namespace App\Http\Controllers;

use App\Services\InvoiceReportService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class InvoiceReportController extends Controller
{
    public function __construct(private readonly InvoiceReportService $service)
    {
    }

    public function summary(Request $request)
    {
        $validated = $request->validate([
            'range' => [
                'sometimes',
                'string',
                Rule::in(['day', 'week', 'month', 'year']),
            ],
        ]);

        $range = $validated['range'] ?? 'day';

        return response()->json($this->service->summary($range));
    }

    public function pastDue()
    {
        return response()->json([
            'data' => $this->service->pastDue(),
        ]);
    }
}

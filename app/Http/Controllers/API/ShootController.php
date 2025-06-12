<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Shoot;

class ShootController extends Controller
{
    public function index()
    {
        $shoots = Shoot::with(['client', 'photographer', 'service'])->get();
        return response()->json(['data' => $shoots]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'client_id' => 'required|exists:users,id',
            'photographer_id' => 'nullable|exists:users,id',
            'service_id' => 'required|exists:services,id',
            'address' => 'required|string',
            'city' => 'required|string',
            'state' => 'required|string',
            'zip' => 'required|string',
            'scheduled_date' => 'required|date',
            'time' => 'required|string',
            'base_quote' => 'required|numeric',
            'tax_amount' => 'required|numeric',
            'total_quote' => 'required|numeric',
            'payment_status' => 'required|string',
            'payment_type' => 'nullable|string',
            'notes' => 'nullable|string',
            'status' => 'required|string',
            'created_by' => 'required|string',
        ]);

        $shoot = Shoot::create($validated);

        return response()->json(['message' => 'Shoot created successfully', 'data' => $shoot], 201);
    }
}

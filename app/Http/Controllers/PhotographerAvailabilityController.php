<?php

namespace App\Http\Controllers;

use App\Models\PhotographerAvailability;
use Illuminate\Http\Request;

class PhotographerAvailabilityController extends Controller
{
    public function index($photographerId)
    {
        $availabilities = PhotographerAvailability::where('photographer_id', $photographerId)->get();
        return response()->json(['data' => $availabilities]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'photographer_id' => 'required|exists:users,id',
            'date' => 'sometimes|date',
            'day_of_week' => 'required_without:date|in:monday,tuesday,wednesday,thursday,friday,saturday,sunday',
            'start_time' => 'required|date_format:H:i',
            'end_time' => 'required|date_format:H:i|after:start_time',
            'status' => 'sometimes|in:available,unavailable',
        ]);

        // Ensure non-null day_of_week to satisfy DB constraint
        $data = $validated;
        if (!isset($data['day_of_week']) && isset($data['date'])) {
            $data['day_of_week'] = strtolower(date('l', strtotime($data['date'])));
        }

        $availability = PhotographerAvailability::create($data);

        return response()->json(['data' => $availability], 201);
    }

    public function destroy($id)
    {
        PhotographerAvailability::findOrFail($id)->delete();
        return response()->json(['message' => 'Availability removed']);
    }

    public function checkAvailability(Request $request)
    {
        $validated = $request->validate([
            'photographer_id' => 'required|exists:users,id',
            'date' => 'required|date',
        ]);

        $dayOfWeek = strtolower(date('l', strtotime($validated['date'])));

        // Specific date overrides first
        $specific = PhotographerAvailability::where('photographer_id', $validated['photographer_id'])
            ->whereDate('date', $validated['date'])
            ->get();

        if ($specific->count() > 0) {
            return response()->json(['data' => $specific]);
        }

        // Fallback to recurring for the weekday
        $recurring = PhotographerAvailability::where('photographer_id', $validated['photographer_id'])
            ->whereNull('date')
            ->where('day_of_week', $dayOfWeek)
            ->get();

        return response()->json(['data' => $recurring]);
    }

    public function update(Request $request, $id)
    {
        $validated = $request->validate([
            'date' => 'sometimes|nullable|date',
            'day_of_week' => 'sometimes|in:monday,tuesday,wednesday,thursday,friday,saturday,sunday',
            'start_time' => 'sometimes|date_format:H:i',
            'end_time' => 'sometimes|date_format:H:i|after:start_time',
            'status' => 'sometimes|in:available,unavailable',
        ]);

        $availability = PhotographerAvailability::findOrFail($id);
        $availability->update($validated);

        return response()->json(['data' => $availability]);
    }

    public function bulkStore(Request $request)
    {
        $validated = $request->validate([
            'photographer_id' => 'required|exists:users,id',
            'availabilities' => 'required|array',
            'availabilities.*.date' => 'sometimes|date',
            'availabilities.*.day_of_week' => 'required_without:availabilities.*.date|in:monday,tuesday,wednesday,thursday,friday,saturday,sunday',
            'availabilities.*.start_time' => 'required|date_format:H:i',
            'availabilities.*.end_time' => 'required|date_format:H:i|after:availabilities.*.start_time',
            'availabilities.*.status' => 'sometimes|in:available,unavailable',
        ]);

        $created = [];

        foreach ($validated['availabilities'] as $availability) {
            $day = $availability['day_of_week'] ?? (isset($availability['date']) ? strtolower(date('l', strtotime($availability['date']))) : null);
            $created[] = PhotographerAvailability::create([
                'photographer_id' => $validated['photographer_id'],
                'date' => $availability['date'] ?? null,
                'day_of_week' => $day,
                'start_time' => $availability['start_time'],
                'end_time' => $availability['end_time'],
                'status' => $availability['status'] ?? 'available',
            ]);
        }

        return response()->json(['data' => $created], 201);
    }

    public function availablePhotographers(Request $request)
    {
        $validated = $request->validate([
            'date' => 'required|date',
            'start_time' => 'required|date_format:H:i',
            'end_time' => 'required|date_format:H:i|after:start_time',
        ]);

        $dayOfWeek = strtolower(date('l', strtotime($validated['date'])));
        // Prefer specific overrides for that date; otherwise use recurring
        $specific = PhotographerAvailability::whereDate('date', $validated['date'])
            ->where('start_time', '<=', $validated['start_time'])
            ->where('end_time', '>=', $validated['end_time'])
            ->where('status', '!=', 'unavailable')
            ->get();

        $specificPhotographerIds = $specific->pluck('photographer_id')->unique();

        // Recurring for others who don't have specific overrides
        $recurring = PhotographerAvailability::whereNull('date')
            ->where('day_of_week', $dayOfWeek)
            ->where('start_time', '<=', $validated['start_time'])
            ->where('end_time', '>=', $validated['end_time'])
            ->whereNotIn('photographer_id', $specificPhotographerIds)
            ->get();

        $merged = $specific->concat($recurring)->values();

        return response()->json(['data' => $merged]);
    }

    public function clearAll($photographerId)
    {
        PhotographerAvailability::where('photographer_id', $photographerId)->delete();
        return response()->json(['message' => 'All availability cleared']);
    }



}

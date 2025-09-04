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
            'day_of_week' => 'required|in:monday,tuesday,wednesday,thursday,friday,saturday,sunday',
            'start_time' => 'required|date_format:H:i',
            'end_time' => 'required|date_format:H:i|after:start_time',
        ]);

        $availability = PhotographerAvailability::create($validated);

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

        $availabilities = PhotographerAvailability::where('photographer_id', $validated['photographer_id'])
            ->where('day_of_week', $dayOfWeek)
            ->get();

        return response()->json(['data' => $availabilities]);
    }

    public function update(Request $request, $id)
    {
        $validated = $request->validate([
            'day_of_week' => 'sometimes|in:monday,tuesday,wednesday,thursday,friday,saturday,sunday',
            'start_time' => 'sometimes|date_format:H:i',
            'end_time' => 'sometimes|date_format:H:i|after:start_time',
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
            'availabilities.*.day_of_week' => 'required|in:monday,tuesday,wednesday,thursday,friday,saturday,sunday',
            'availabilities.*.start_time' => 'required|date_format:H:i',
            'availabilities.*.end_time' => 'required|date_format:H:i|after:availabilities.*.start_time',
        ]);

        $created = [];

        foreach ($validated['availabilities'] as $availability) {
            $created[] = PhotographerAvailability::create([
                'photographer_id' => $validated['photographer_id'],
                'day_of_week' => $availability['day_of_week'],
                'start_time' => $availability['start_time'],
                'end_time' => $availability['end_time'],
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

        $availabilities = PhotographerAvailability::where('day_of_week', $dayOfWeek)
            ->where('start_time', '<=', $validated['start_time'])
            ->where('end_time', '>=', $validated['end_time'])
            ->get();

        return response()->json(['data' => $availabilities]);
    }

    public function clearAll($photographerId)
    {
        PhotographerAvailability::where('photographer_id', $photographerId)->delete();
        return response()->json(['message' => 'All availability cleared']);
    }



}

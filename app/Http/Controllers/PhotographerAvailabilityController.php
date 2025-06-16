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
}

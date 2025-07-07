<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\Shoot;

class PhotographerShootController extends Controller
{
    //
    public function index()
    {
        $photographerId = Auth::id();

        $shoots = Shoot::with(['client', 'service','photographer', 'files'])
                        ->get();

        return response()->json([
            'data' => $shoots
        ]);
    }
}

<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Shoot;
use Illuminate\Support\Facades\Storage;

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

    public function uploadFiles(Request $request, $shootId)
    {
        // Validate the request
        $request->validate([
            'files' => 'required|array',
            'files.*' => 'required|file|max:10240|mimes:jpeg,jpg,png,gif,mp4,mov,avi', // 10MB max per file
        ]);

        // Find the shoot
        $shoot = Shoot::findOrFail($shootId);
        
        $uploadedFiles = [];

        try {
            foreach ($request->file('files') as $file) {
                // Generate unique filename
                $originalName = $file->getClientOriginalName();
                $extension = $file->getClientOriginalExtension();
                $filename = time() . '_' . uniqid() . '.' . $extension;
                
                // Store the file
                $path = $file->storeAs('shoots/' . $shootId, $filename, 'public');
                
                // Save file information to database (assuming you have a files table)
                $fileRecord = $shoot->files()->create([
                    'filename' => $originalName,
                    'stored_filename' => $filename,
                    'path' => $path,
                    'file_type' => $file->getMimeType(),
                    'file_size' => $file->getSize(),
                    'uploaded_by' => auth()->id(),
                ]);

                $uploadedFiles[] = [
                    'id' => $fileRecord->id,
                    'filename' => $originalName,
                    'path' => $path,
                    'url' => Storage::url($path),
                ];
            }

            return response()->json([
                'message' => 'Files uploaded successfully.',
                'files' => $uploadedFiles,
                'count' => count($uploadedFiles)
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to upload files.',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}

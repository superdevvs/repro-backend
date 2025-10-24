<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Shoot;
use App\Models\ShootFile;
use App\Models\User;
use App\Services\DropboxWorkflowService;
use App\Services\MailService;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;

class ShootController extends Controller
{
    protected $dropboxService;
    protected $mailService;

    public function __construct(DropboxWorkflowService $dropboxService, MailService $mailService)
    {
        $this->dropboxService = $dropboxService;
        $this->mailService = $mailService;
    }

    public function index()
    {
        $user = auth()->user();
        $query = Shoot::with(['client', 'photographer', 'service', 'files', 'payments']);

        // Filter based on user role
        if ($user->role === 'photographer') {
            $query->where('photographer_id', $user->id);
        } elseif ($user->role === 'client') {
            $query->where('client_id', $user->id);
        }

        $shoots = $query->orderBy('scheduled_date', 'desc')->get();
        
        return response()->json(['data' => $shoots]);
    }

    public function show($id)
    {
        $shoot = Shoot::with([
            'client', 'photographer', 'service', 'files', 'payments', 
            'dropboxFolders', 'workflowLogs.user', 'verifiedBy'
        ])->findOrFail($id);

        return response()->json(['data' => $shoot]);
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
            'scheduled_date' => 'nullable|date',
            'time' => 'nullable|string',
            'base_quote' => 'required|numeric',
            'tax_amount' => 'required|numeric',
            'total_quote' => 'required|numeric',
            'payment_status' => 'nullable|string',
            'payment_type' => 'nullable|string',
            'notes' => 'nullable|string',
            'status' => 'nullable|string',
            'created_by' => 'nullable|string',
            'service_category' => 'nullable|string|in:P,iGuide,Video',
        ]);

        // Set default values for optional fields and normalize status values
        $validated['payment_status'] = $this->normalizePaymentStatus($validated['payment_status'] ?? 'unpaid');
        // Compute shoot status: scheduled if both date and time provided, else on_hold
        $hasDate = !empty($validated['scheduled_date']);
        $hasTime = !empty($validated['time']);
        $validated['status'] = ($hasDate && $hasTime) ? 'scheduled' : 'on_hold';
        $validated['created_by'] = $validated['created_by'] ?? auth()->user()->name ?? 'System';

        DB::beginTransaction();
        try {
            $shoot = Shoot::create($validated);

            // Create Dropbox folders only when a date/time is set (scheduled)
            if ($hasDate && $hasTime) {
                $this->dropboxService->createShootFolders($shoot);
            }
            
            // Log the creation
            $shoot->workflowLogs()->create([
                'user_id' => auth()->id(),
                'action' => 'shoot_created',
                'details' => 'Shoot booked and Dropbox folders created',
                'metadata' => [
                    'client_id' => $shoot->client_id,
                    'photographer_id' => $shoot->photographer_id,
                    'scheduled_date' => $shoot->scheduled_date->toDateString()
                ]
            ]);

            // Send shoot scheduled email to client only if scheduled
            if ($shoot->status === 'scheduled') {
                $client = User::find($shoot->client_id);
                if ($client) {
                    $paymentLink = $this->mailService->generatePaymentLink($shoot);
                    $this->mailService->sendShootScheduledEmail($client, $shoot, $paymentLink);
                }
            }

            DB::commit();
            return response()->json(['message' => 'Shoot created successfully', 'data' => $shoot], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Failed to create shoot', 'error' => $e->getMessage()], 500);
        }
    }

    public function uploadFiles(Request $request, $shootId)
    {
        $request->validate([
            'files' => 'required|array',
            'files.*' => 'required|file|max:51200|mimes:jpeg,jpg,png,gif,mp4,mov,avi,raw,cr2,nef,arw', // 50MB max per file
            'service_category' => 'nullable|string|in:P,iGuide,Video'
        ]);

        $shoot = Shoot::findOrFail($shootId);
        
        // Check if user can upload photos
        if (!$shoot->canUploadPhotos()) {
            return response()->json([
                'message' => 'Cannot upload photos at this workflow stage',
                'current_status' => $shoot->workflow_status
            ], 400);
        }

        $uploadedFiles = [];
        $errors = [];

        DB::beginTransaction();
        try {
            foreach ($request->file('files') as $file) {
                try {
                    $serviceCategory = $request->input('service_category');
                    $shootFile = $this->dropboxService->uploadToTodo($shoot, $file, auth()->id(), $serviceCategory);
                    
                    $uploadedFiles[] = [
                        'id' => $shootFile->id,
                        'filename' => $shootFile->filename,
                        'workflow_stage' => $shootFile->workflow_stage,
                        'dropbox_path' => $shootFile->dropbox_path,
                        'file_size' => $shootFile->file_size,
                        'uploaded_at' => $shootFile->created_at
                    ];
                } catch (\Exception $e) {
                    $errors[] = [
                        'filename' => $file->getClientOriginalName(),
                        'error' => $e->getMessage()
                    ];
                }
            }

            DB::commit();

            return response()->json([
                'message' => 'Files processed',
                'uploaded_files' => $uploadedFiles,
                'errors' => $errors,
                'success_count' => count($uploadedFiles),
                'error_count' => count($errors),
                'shoot_status' => $shoot->fresh()->workflow_status
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Failed to upload files',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function moveFileToCompleted(Request $request, $shootId, $fileId)
    {
        $shoot = Shoot::findOrFail($shootId);
        $file = ShootFile::where('shoot_id', $shootId)->findOrFail($fileId);

        if (!$file->canMoveToCompleted()) {
            return response()->json([
                'message' => 'File cannot be moved to completed at this stage',
                'current_stage' => $file->workflow_stage
            ], 400);
        }

        try {
            $this->dropboxService->moveToCompleted($file, auth()->id());
            
            return response()->json([
                'message' => 'File moved to completed folder successfully',
                'file' => $file->fresh(),
                'shoot_status' => $shoot->fresh()->workflow_status
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to move file to completed folder',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function verifyFile(Request $request, $shootId, $fileId)
    {
        $request->validate([
            'verification_notes' => 'nullable|string|max:1000'
        ]);

        $shoot = Shoot::findOrFail($shootId);
        $file = ShootFile::where('shoot_id', $shootId)->findOrFail($fileId);

        if (!$file->canVerify()) {
            return response()->json([
                'message' => 'File cannot be verified at this stage',
                'current_stage' => $file->workflow_stage
            ], 400);
        }

        // Check if user has admin permissions
        if (!in_array(auth()->user()->role, ['admin', 'super_admin'])) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        DB::beginTransaction();
        try {
            $file->verify(auth()->id(), $request->verification_notes);
            
            // Move to final folder and store on server
            $this->dropboxService->moveToFinal($file, auth()->id());
            
            // Check if all files are verified
            $unverifiedFiles = $shoot->files()->where('workflow_stage', '!=', ShootFile::STAGE_VERIFIED)->count();
            if ($unverifiedFiles === 0 && $shoot->workflow_status === Shoot::WORKFLOW_EDITING_COMPLETE) {
                $shoot->updateWorkflowStatus(Shoot::WORKFLOW_ADMIN_VERIFIED, auth()->id());
                
                // Send shoot ready email to client
                $client = User::find($shoot->client_id);
                if ($client) {
                    $this->mailService->sendShootReadyEmail($client, $shoot);
                }
            }

            DB::commit();

            return response()->json([
                'message' => 'File verified and moved to final storage successfully',
                'file' => $file->fresh(),
                'shoot_status' => $shoot->fresh()->workflow_status
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Failed to verify file',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function getWorkflowStatus($shootId)
    {
        $shoot = Shoot::with(['files', 'workflowLogs.user'])->findOrFail($shootId);
        
        $fileStats = [
            'total' => $shoot->files->count(),
            'todo' => $shoot->files->where('workflow_stage', ShootFile::STAGE_TODO)->count(),
            'completed' => $shoot->files->where('workflow_stage', ShootFile::STAGE_COMPLETED)->count(),
            'verified' => $shoot->files->where('workflow_stage', ShootFile::STAGE_VERIFIED)->count(),
        ];

        return response()->json([
            'shoot_id' => $shoot->id,
            'workflow_status' => $shoot->workflow_status,
            'file_stats' => $fileStats,
            'workflow_logs' => $shoot->workflowLogs->take(10),
            'can_upload_photos' => $shoot->canUploadPhotos(),
            'can_move_to_completed' => $shoot->canMoveToCompleted(),
            'can_verify' => $shoot->canVerify()
        ]);
    }

    /**
     * Normalize payment status to valid values
     */
    private function normalizePaymentStatus($status)
    {
        $statusMap = [
            'paid' => 'paid',
            'unpaid' => 'unpaid',
            'partial' => 'partial',
            'pending' => 'unpaid',
            'complete' => 'paid',
            'completed' => 'paid',
        ];

        return $statusMap[strtolower($status)] ?? 'unpaid';
    }

    /**
     * Normalize shoot status to valid values
     */
    private function normalizeStatus($status)
    {
        $statusMap = [
            'booked' => 'booked',
            'cancelled' => 'cancelled',
            'completed' => 'completed',
            'active' => 'booked',
            'pending' => 'booked',
            'scheduled' => 'booked',
            'in_progress' => 'booked',
            'done' => 'completed',
            'finished' => 'completed',
        ];

        return $statusMap[strtolower($status)] ?? 'booked';
    }
}

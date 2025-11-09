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
            'shoot_notes' => 'nullable|string',
            'company_notes' => 'nullable|string',
            'photographer_notes' => 'nullable|string',
            'editor_notes' => 'nullable|string',
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
            // Map legacy 'notes' to 'shoot_notes' if provided and specific fields are empty
            if (!empty($validated['notes'])) {
                $validated['shoot_notes'] = $validated['shoot_notes'] ?? $validated['notes'];
            }
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

    /**
     * Minimal update endpoint: allow admins to update status and dates.
     */
    public function update(Request $request, $shootId)
    {
        $user = auth()->user();
        if (!$user || !in_array($user->role, ['admin','superadmin','super_admin'])) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $validated = $request->validate([
            'status' => 'nullable|string|in:booked,scheduled,completed,on_hold,cancelled',
            'workflow_status' => 'nullable|string|in:booked,photos_uploaded,editing_complete,admin_verified,completed',
            'scheduled_date' => 'nullable|date',
            'time' => 'nullable|string',
        ]);

        $shoot = Shoot::findOrFail($shootId);

        if (array_key_exists('status', $validated)) {
            $shoot->status = $validated['status'];
        }
        // If marking completed via either status or workflow_status, stamp admin_verified_at
        $markCompleted = false;
        if (array_key_exists('scheduled_date', $validated)) {
            $shoot->scheduled_date = $validated['scheduled_date'];
        }
        if (array_key_exists('time', $validated)) {
            $shoot->time = $validated['time'];
        }

        if (array_key_exists('workflow_status', $validated)) {
            $shoot->workflow_status = $validated['workflow_status'];
            if ($validated['workflow_status'] === 'completed' || $validated['workflow_status'] === 'admin_verified') {
                $markCompleted = true;
            }
        }

        if (array_key_exists('status', $validated) && $validated['status'] === 'completed') {
            $markCompleted = true;
        }

        $shoot->save();

        if ($markCompleted) {
            // Set admin_verified_at if not already set
            if (empty($shoot->admin_verified_at)) {
                $shoot->admin_verified_at = now();
                $shoot->save();
            }
            // Ensure workflow_status reflects completion
            if ($shoot->workflow_status !== 'completed') {
                $shoot->workflow_status = 'completed';
                $shoot->save();
            }
        }

        return response()->json([
            'message' => 'Shoot updated',
            'data' => $shoot->fresh(['client','photographer','service','files'])
        ]);
    }

    public function uploadFiles(Request $request, $shootId)
    {
        $request->validate([
            'files' => 'required|array',
            // Align with main upload controller: allow up to ~1 GiB and extended mimes
            'files.*' => 'required|file|max:1048576|mimes:jpeg,jpg,png,gif,mp4,mov,avi,raw,cr2,nef,arw,tiff,bmp,heic,heif,zip',
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
     * Finalize a shoot: take all edited (completed-stage) files, copy to server final storage,
     * mark them verified, and advance the shoot workflow. Meant for an admin toggle action.
     */
    public function finalize(Request $request, $shootId)
    {
        $user = auth()->user();
        if (!$user || !in_array($user->role, ['admin','superadmin','super_admin'])) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $request->validate([
            'final_status' => 'nullable|string|in:admin_verified,completed'
        ]);

        $shoot = Shoot::with(['files'])->findOrFail($shootId);
        $finalStatus = $request->input('final_status', Shoot::WORKFLOW_ADMIN_VERIFIED);

        $completedFiles = $shoot->files()->where('workflow_stage', ShootFile::STAGE_COMPLETED)->get();

        if ($completedFiles->isEmpty()) {
            return response()->json([
                'message' => 'No edited files to finalize',
                'data' => $shoot->only(['id','workflow_status'])
            ], 400);
        }

        try {
            foreach ($completedFiles as $file) {
                // Move/copy to server final storage and mark verified
                $this->dropboxService->moveToFinal($file, $user->id);
            }

            // Advance workflow status
            if ($finalStatus === Shoot::WORKFLOW_COMPLETED) {
                // Ensure admin_verified_at is set first
                if (empty($shoot->admin_verified_at)) {
                    $shoot->admin_verified_at = now();
                }
                $shoot->workflow_status = Shoot::WORKFLOW_COMPLETED;
            } else {
                $shoot->updateWorkflowStatus(Shoot::WORKFLOW_ADMIN_VERIFIED, $user->id);
            }
            $shoot->save();

            return response()->json([
                'message' => 'Shoot finalized successfully',
                'data' => $shoot->fresh(['files'])
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to finalize shoot',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Simplified notes updater: directly updates any provided note fields
     */
    public function updateNotesSimple(Request $request, $shootId)
    {
        $shoot = Shoot::findOrFail($shootId);

        $request->validate([
            'shoot_notes' => 'nullable|string',
            'company_notes' => 'nullable|string',
            'photographer_notes' => 'nullable|string',
            'editor_notes' => 'nullable|string',
        ]);

        $data = $request->only(['shoot_notes','company_notes','photographer_notes','editor_notes']);

        // Also accept common camelCase keys
        $camel = [
            'shootNotes' => 'shoot_notes',
            'companyNotes' => 'company_notes',
            'photographerNotes' => 'photographer_notes',
            'editingNotes' => 'editor_notes',
            'editorNotes' => 'editor_notes',
        ];
        foreach ($camel as $from => $to) {
            if ($request->has($from) && !array_key_exists($to, $data)) {
                $data[$to] = $request->input($from);
            }
        }

        if (!empty($data)) {
            $shoot->fill($data);
            $shoot->save();
        }

        return response()->json([
            'message' => empty($data) ? 'No changes detected' : 'Notes updated',
            'data' => $shoot->only(['id','shoot_notes','company_notes','photographer_notes','editor_notes'])
        ]);
    }

    /**
     * Update notes on a shoot with role-based permissions
     */
    public function updateNotes(Request $request, $shootId)
    {
        $shoot = Shoot::findOrFail($shootId);

        $user = $request->user();
        $role = strtolower($user->role ?? '');
        $role = str_replace('-', '_', $role);
        if ($role === 'superadmin') { $role = 'super_admin'; }

        $request->validate([
            'shoot_notes' => 'nullable|string',
            'company_notes' => 'nullable|string',
            'photographer_notes' => 'nullable|string',
            'editor_notes' => 'nullable|string',
        ]);

        $allowed = [];
        if (in_array($role, ['admin', 'super_admin'])) {
            $allowed = ['shoot_notes', 'company_notes', 'photographer_notes', 'editor_notes'];
        } elseif ($role === 'client') {
            $allowed = ['shoot_notes'];
        } elseif ($role === 'photographer') {
            $allowed = ['photographer_notes'];
        } elseif ($role === 'editor') {
            $allowed = ['editor_notes'];
        }

        if (empty($allowed)) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        // Build updates allowing empty strings and camelCase keys; update only allowed fields
        $input = $request->all();
        // Map camelCase to snake_case if provided
        $synonyms = [
            'shootNotes' => 'shoot_notes',
            'companyNotes' => 'company_notes',
            'photographerNotes' => 'photographer_notes',
            'editingNotes' => 'editor_notes',
            'editorNotes' => 'editor_notes',
        ];
        foreach ($synonyms as $from => $to) {
            if (array_key_exists($from, $input) && !array_key_exists($to, $input)) {
                $input[$to] = $input[$from];
            }
        }

        // Also accept a nested `notes` object payload and flatten it
        if (array_key_exists('notes', $input)) {
            $notesPayload = $input['notes'];
            if (is_string($notesPayload)) {
                // Treat as shoot notes string
                $input['shoot_notes'] = $notesPayload;
            } elseif (is_array($notesPayload)) {
                foreach ($synonyms as $from => $to) {
                    if (array_key_exists($from, $notesPayload) && !array_key_exists($to, $input)) {
                        $input[$to] = $notesPayload[$from];
                    }
                }
                // Also allow snake_case inside notes
                foreach (['shoot_notes','company_notes','photographer_notes','editor_notes'] as $field) {
                    if (array_key_exists($field, $notesPayload) && !array_key_exists($field, $input)) {
                        $input[$field] = $notesPayload[$field];
                    }
                }
            }
        }

        $candidateFields = ['shoot_notes','company_notes','photographer_notes','editor_notes'];
        $updates = [];
        foreach ($candidateFields as $field) {
            if (array_key_exists($field, $input)) {
                // Only include if role is allowed to change this field
                if (in_array($field, $allowed)) {
                    $updates[$field] = $input[$field];
                } else {
                    // If an unallowed field is present, ignore it silently to avoid UX errors
                    continue;
                }
            }
        }

        // If nothing to update (no provided fields or none allowed), respond OK to avoid blocking UI
        if (empty($updates)) {
            return response()->json([
                'message' => 'No changes detected',
                'data' => $shoot->only(['id','shoot_notes','company_notes','photographer_notes','editor_notes'])
            ]);
        }

        foreach ($updates as $k => $v) {
            $shoot->{$k} = $v; // allow empty string to overwrite
        }
        $shoot->save();

        return response()->json([
            'message' => 'Notes updated',
            'data' => $shoot->only(['id','shoot_notes','company_notes','photographer_notes','editor_notes'])
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

    // ----- Public assets (read-only, no auth) -----
    private function buildPublicAssets(\App\Models\Shoot $shoot)
    {
        // Prefer verified files; if none, fall back to completed
        $files = $shoot->files;
        $verified = $files->where('workflow_stage', \App\Models\ShootFile::STAGE_VERIFIED);
        $completed = $files->where('workflow_stage', \App\Models\ShootFile::STAGE_COMPLETED);
        $chosen = $verified->count() > 0 ? $verified : $completed;

        $mapUrl = function($file) {
            $path = $file->path ?? '';
            if (!$path) return null;
            // If already an absolute URL, return as-is
            if (preg_match('/^https?:\/\//i', $path)) return $path;

            // Only expose files that exist on public disk to avoid returning Dropbox API paths
            $clean = ltrim($path, '/');
            // Normalize potential variants
            $publicRelative = str_starts_with($clean, 'storage/') ? substr($clean, 8) : $clean; // remove leading 'storage/'
            if (Storage::disk('public')->exists($publicRelative)) {
                $url = Storage::disk('public')->url($publicRelative);
                // Ensure absolute URL
                if (!preg_match('/^https?:\/\//i', $url)) {
                    $base = rtrim(config('app.url'), '/');
                    $url = $base . '/' . ltrim($url, '/');
                }
                return $url;
            }
            return null; // skip non-local files (e.g., pure Dropbox paths)
        };

        $photos = [];
        $videos = [];
        foreach ($chosen as $f) {
            $url = $mapUrl($f);
            if (!$url) continue;
            $type = strtolower((string) $f->file_type);
            if (str_starts_with($type, 'image/')) {
                $photos[] = $url;
            } elseif (str_starts_with($type, 'video/')) {
                $videos[] = $url;
            } else {
                // check by extension as fallback
                $ext = strtolower(pathinfo($f->filename ?? $f->stored_filename ?? '', PATHINFO_EXTENSION));
                if (in_array($ext, ['jpg','jpeg','png','gif','bmp','tif','tiff','heic','heif'])) {
                    $photos[] = $url;
                } elseif (in_array($ext, ['mp4','mov','avi','webm','ogg'])) {
                    $videos[] = $url;
                }
            }
        }

        return [
            'shoot' => [
                'id' => $shoot->id,
                'client_name' => optional($shoot->client)->name,
                'client_company' => optional($shoot->client)->company_name,
                'address' => $shoot->address,
                'city' => $shoot->city,
                'state' => $shoot->state,
                'zip' => $shoot->zip,
                'scheduled_date' => optional($shoot->scheduled_date)->toDateString(),
            ],
            'photos' => array_values(array_unique($photos)),
            'videos' => array_values(array_unique($videos)),
            // Placeholder for 3D tour links if stored later
            'tours' => [
                'matterport' => null,
                'iGuide' => null,
                'cubicasa' => null,
            ],
        ];
    }

    public function publicBranded($shootId)
    {
        $shoot = \App\Models\Shoot::with(['files','client'])->findOrFail($shootId);
        $assets = $this->buildPublicAssets($shoot);
        $assets['type'] = 'branded';
        return response()->json($assets);
    }

    public function publicMls($shootId)
    {
        $shoot = \App\Models\Shoot::with(['files','client'])->findOrFail($shootId);
        $assets = $this->buildPublicAssets($shoot);
        $assets['type'] = 'mls';
        return response()->json($assets);
    }

    public function publicGenericMls($shootId)
    {
        $shoot = \App\Models\Shoot::with(['files','client'])->findOrFail($shootId);
        $assets = $this->buildPublicAssets($shoot);
        $assets['type'] = 'generic-mls';
        return response()->json($assets);
    }

    /**
     * Public client profile: basic client info and their shoots with previewable assets.
     * No auth required so links can be shared.
     */
    public function publicClientProfile($clientId)
    {
        $client = \App\Models\User::findOrFail($clientId);

        // Only include shoots that have at least one verified (finalized) file
        $shoots = Shoot::with(['files' => function($q) {
                $q->where('workflow_stage', \App\Models\ShootFile::STAGE_VERIFIED);
            }])
            ->where('client_id', $client->id)
            ->whereHas('files', function($q) {
                $q->where('workflow_stage', \App\Models\ShootFile::STAGE_VERIFIED);
            })
            ->orderByDesc('scheduled_date')
            ->get();

        $mapUrl = function($path) {
            if (!$path) return null;
            if (preg_match('/^https?:\/\//i', $path)) return $path;
            $clean = ltrim($path, '/');
            $publicRelative = str_starts_with($clean, 'storage/') ? substr($clean, 8) : $clean;
            if (\Storage::disk('public')->exists($publicRelative)) {
                $url = \Storage::disk('public')->url($publicRelative);
                if (!preg_match('/^https?:\/\//i', $url)) {
                    $base = rtrim(config('app.url'), '/');
                    $url = $base . '/' . ltrim($url, '/');
                }
                return $url;
            }
            return null;
        };

        $shootItems = $shoots->map(function ($s) use ($mapUrl) {
            $files = $s->files ?: collect();
            // Files are already filtered to verified in eager load, but keep guards
            $imageFile = $files->first(function ($f) { return str_starts_with(strtolower((string)$f->file_type), 'image/'); });
            $preview = $imageFile ? ($mapUrl($imageFile->path) ?: $mapUrl($imageFile->dropbox_path)) : null;

            return [
                'id' => $s->id,
                'address' => $s->address,
                'city' => $s->city,
                'state' => $s->state,
                'zip' => $s->zip,
                'scheduled_date' => optional($s->scheduled_date)->toDateString(),
                'files_count' => $files->count(),
                'preview_image' => $preview,
            ];
        });

        return response()->json([
            'client' => [
                'id' => $client->id,
                'name' => $client->name,
                'email' => $client->email,
                'company_name' => $client->company_name,
                'phonenumber' => $client->phonenumber,
                'avatar' => $client->avatar,
            ],
            'shoots' => $shootItems,
        ]);
    }
}

<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Shoot;
use App\Services\DropboxWorkflowService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class FileUploadController extends Controller
{
    protected $dropboxService;

    public function __construct(DropboxWorkflowService $dropboxService)
    {
        $this->dropboxService = $dropboxService;
    }

    /**
     * Upload files from PC to shoot folder
     */
    public function uploadFromPC(Request $request, $shootId)
    {
        $request->validate([
            'files' => 'required|array',
            'files.*' => 'required|file|max:102400|mimes:jpeg,jpg,png,gif,mp4,mov,avi,raw,cr2,nef,arw,tiff,bmp', // 100MB max
            'service_category' => 'nullable|string|in:P,iGuide,Video',
            'upload_type' => 'nullable|string|in:raw,edited'
        ]);

        $shoot = Shoot::findOrFail($shootId);
        $uploadType = $request->input('upload_type', 'raw');
        
        // Check workflow permissions based on upload type
        if ($uploadType === 'raw' && !$shoot->canUploadPhotos()) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot upload raw photos at this workflow stage',
                'current_status' => $shoot->workflow_status
            ], 400);
        }

        $uploadedFiles = [];
        $errors = [];

        try {
            foreach ($request->file('files') as $file) {
                try {
                    $serviceCategory = $request->input('service_category', 'P');
                    
                    if ($uploadType === 'raw') {
                        // Upload to ToDo folder
                        $shootFile = $this->dropboxService->uploadToTodo($shoot, $file, auth()->id(), $serviceCategory);
                    } else {
                        // Upload directly to Completed folder (for edited files)
                        $shootFile = $this->dropboxService->uploadToCompleted($shoot, $file, auth()->id(), $serviceCategory);
                    }
                    
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

            return response()->json([
                'success' => true,
                'message' => 'Files processed successfully',
                'uploaded_files' => $uploadedFiles,
                'errors' => $errors,
                'success_count' => count($uploadedFiles),
                'error_count' => count($errors)
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Upload failed: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get user's Dropbox files for selection
     */
    public function listDropboxFiles(Request $request)
    {
        $request->validate([
            'path' => 'nullable|string',
            'cursor' => 'nullable|string'
        ]);

        $path = $request->input('path', '');
        $cursor = $request->input('cursor');

        try {
            $accessToken = config('services.dropbox.access_token');
            
            if ($cursor) {
                // Continue listing with cursor
                $response = Http::withToken($accessToken)
                    ->withOptions(['verify' => config('app.env') === 'production'])
                    ->withHeaders(['Content-Type' => 'application/json'])
                    ->withBody(json_encode(['cursor' => $cursor]))
                    ->post('https://api.dropboxapi.com/2/files/list_folder/continue');
            } else {
                // Initial listing
                $response = Http::withToken($accessToken)
                    ->withOptions(['verify' => config('app.env') === 'production'])
                    ->withHeaders(['Content-Type' => 'application/json'])
                    ->withBody(json_encode([
                        'path' => $path === '/' ? '' : $path,
                        'recursive' => false,
                        'include_media_info' => true,
                        'include_deleted' => false,
                        'include_has_explicit_shared_members' => false
                    ]))
                    ->post('https://api.dropboxapi.com/2/files/list_folder');
            }

            if ($response->successful()) {
                $data = $response->json();
                
                // Filter and format files
                $files = [];
                foreach ($data['entries'] as $entry) {
                    if ($entry['.tag'] === 'file') {
                        // Check if it's an image or video file
                        $extension = strtolower(pathinfo($entry['name'], PATHINFO_EXTENSION));
                        $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'mp4', 'mov', 'avi', 'raw', 'cr2', 'nef', 'arw', 'tiff', 'bmp'];
                        
                        if (in_array($extension, $allowedExtensions)) {
                            $files[] = [
                                'id' => $entry['id'],
                                'name' => $entry['name'],
                                'path_lower' => $entry['path_lower'],
                                'path_display' => $entry['path_display'],
                                'size' => $entry['size'],
                                'client_modified' => $entry['client_modified'] ?? null,
                                'server_modified' => $entry['server_modified'],
                                'is_downloadable' => $entry['is_downloadable'] ?? true,
                                'extension' => $extension,
                                'file_type' => $this->getFileType($extension)
                            ];
                        }
                    } elseif ($entry['.tag'] === 'folder') {
                        $files[] = [
                            'id' => $entry['id'],
                            'name' => $entry['name'],
                            'path_lower' => $entry['path_lower'],
                            'path_display' => $entry['path_display'],
                            'is_folder' => true
                        ];
                    }
                }

                return response()->json([
                    'success' => true,
                    'files' => $files,
                    'has_more' => $data['has_more'],
                    'cursor' => $data['cursor'] ?? null,
                    'current_path' => $path
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to list Dropbox files',
                    'error' => $response->json()
                ], $response->status());
            }
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error listing Dropbox files: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Copy files from user's Dropbox to shoot folder
     */
    public function copyFromDropbox(Request $request, $shootId)
    {
        $request->validate([
            'files' => 'required|array',
            'files.*.path' => 'required|string',
            'files.*.name' => 'required|string',
            'service_category' => 'nullable|string|in:P,iGuide,Video'
        ]);

        $shoot = Shoot::findOrFail($shootId);
        
        if (!$shoot->canUploadPhotos()) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot upload photos at this workflow stage',
                'current_status' => $shoot->workflow_status
            ], 400);
        }

        $copiedFiles = [];
        $errors = [];

        try {
            foreach ($request->input('files') as $fileInfo) {
                try {
                    $serviceCategory = $request->input('service_category');
                    $shootFile = $this->dropboxService->copyFromDropboxToTodo(
                        $shoot, 
                        $fileInfo['path'], 
                        $fileInfo['name'], 
                        auth()->id(), 
                        $serviceCategory
                    );
                    
                    $copiedFiles[] = [
                        'id' => $shootFile->id,
                        'filename' => $shootFile->filename,
                        'workflow_stage' => $shootFile->workflow_stage,
                        'dropbox_path' => $shootFile->dropbox_path,
                        'file_size' => $shootFile->file_size,
                        'uploaded_at' => $shootFile->created_at
                    ];
                } catch (\Exception $e) {
                    $errors[] = [
                        'filename' => $fileInfo['name'],
                        'error' => $e->getMessage()
                    ];
                }
            }

            return response()->json([
                'success' => true,
                'message' => 'Files copied successfully',
                'copied_files' => $copiedFiles,
                'errors' => $errors,
                'success_count' => count($copiedFiles),
                'error_count' => count($errors)
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Copy failed: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get file type category from extension
     */
    private function getFileType($extension)
    {
        $imageExtensions = ['jpg', 'jpeg', 'png', 'gif', 'raw', 'cr2', 'nef', 'arw', 'tiff', 'bmp'];
        $videoExtensions = ['mp4', 'mov', 'avi'];

        if (in_array($extension, $imageExtensions)) {
            return 'image';
        } elseif (in_array($extension, $videoExtensions)) {
            return 'video';
        }

        return 'unknown';
    }
}
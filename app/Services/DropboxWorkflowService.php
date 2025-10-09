<?php

namespace App\Services;

use App\Models\Shoot;
use App\Models\ShootFile;
use App\Models\DropboxFolder;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\UploadedFile;

class DropboxWorkflowService
{
    protected $tokenService;
    protected $dropboxApiUrl = 'https://api.dropboxapi.com/2';
    protected $dropboxContentUrl = 'https://content.dropboxapi.com/2';
    protected $httpOptions;

    public function __construct(DropboxTokenService $tokenService = null)
    {
        $this->tokenService = $tokenService ?: new DropboxTokenService();
        
        // Configure HTTP options for development environment
        $this->httpOptions = [
            'verify' => config('app.env') === 'production' ? true : false,
            'timeout' => 60,
        ];
    }

    /**
     * Get a valid access token
     */
    protected function getAccessToken()
    {
        try {
            return $this->tokenService->getValidAccessToken();
        } catch (\Exception $e) {
            Log::error('Failed to get valid Dropbox access token', ['error' => $e->getMessage()]);
            throw new \Exception('Dropbox authentication failed. Please check your token configuration.');
        }
    }

    /**
     * Create folder structure for a shoot with date-based organization
     */
    public function createShootFolders(Shoot $shoot)
    {
        $dateFolder = $shoot->scheduled_date->format('Y-m-d');
        $addressFolder = $this->generateAddressFolderName($shoot);
        
        // Determine service categories to create folders for
        $serviceCategories = $this->getServiceCategories($shoot);
        
        $basePath = "/RealEstatePhotos";
        
        // Create base RealEstatePhotos folder
        $this->createFolderIfNotExists($basePath);
        
        // Create ToDo and Completed base folders with test markers
        $todoBasePath = "{$basePath}/ToDoTest1";
        $completedBasePath = "{$basePath}/CompletedTest1";
        
        $this->createFolderIfNotExists($todoBasePath);
        $this->createFolderIfNotExists($completedBasePath);
        
        // Create date folders inside ToDo and Completed
        $todoDatePath = "{$todoBasePath}/{$dateFolder}";
        $completedDatePath = "{$completedBasePath}/{$dateFolder}";
        
        $this->createFolderIfNotExists($todoDatePath);
        $this->createFolderIfNotExists($completedDatePath);
        
        // Create category-specific folders for each service type
        foreach ($serviceCategories as $category) {
            $categoryFolderName = $this->getCategoryPrefix($category) . '-' . $addressFolder;
            
            $todoPath = "{$todoDatePath}/{$categoryFolderName}";
            $completedPath = "{$completedDatePath}/{$categoryFolderName}";
            
            // Create ToDo category folder
            if ($this->createFolderIfNotExists($todoPath)) {
                DropboxFolder::create([
                    'shoot_id' => $shoot->id,
                    'folder_type' => DropboxFolder::TYPE_TODO,
                    'service_category' => $category,
                    'dropbox_path' => $todoPath,
                    'dropbox_folder_id' => null
                ]);
            }
            
            // Create Completed category folder
            if ($this->createFolderIfNotExists($completedPath)) {
                DropboxFolder::create([
                    'shoot_id' => $shoot->id,
                    'folder_type' => DropboxFolder::TYPE_COMPLETED,
                    'service_category' => $category,
                    'dropbox_path' => $completedPath,
                    'dropbox_folder_id' => null
                ]);
            }
        }
        
        // Note: Final files will be stored on server only, no Dropbox final folder needed
    }

    /**
     * Upload file to ToDo folder
     */
    public function uploadToTodo(Shoot $shoot, UploadedFile $file, $userId, $serviceCategory = null)
    {
        // Determine service category if not provided
        if (!$serviceCategory) {
            $serviceCategories = $this->getServiceCategories($shoot);
            $serviceCategory = $serviceCategories[0]; // Use first category as default
        }
        
        $todoFolder = $shoot->dropboxFolders()
            ->where('folder_type', DropboxFolder::TYPE_TODO)
            ->where('service_category', $serviceCategory)
            ->first();
        
        if (!$todoFolder) {
            $this->createShootFolders($shoot);
            $todoFolder = $shoot->dropboxFolders()
                ->where('folder_type', DropboxFolder::TYPE_TODO)
                ->where('service_category', $serviceCategory)
                ->first();
        }

        if (!$todoFolder) {
            throw new \Exception("ToDo folder not found for category: {$serviceCategory}");
        }

        $filename = 'TODO_' . time() . '_' . $file->getClientOriginalName();
        $dropboxPath = $todoFolder->dropbox_path . '/' . $filename;

        try {
            $fileContent = $file->get();
            
            $apiArgs = json_encode([
                'path' => $dropboxPath,
                'mode' => 'add',
                'autorename' => true,
                'mute' => false,
            ]);

            $response = Http::withToken($this->getAccessToken())
                ->withOptions($this->httpOptions)
                ->withBody($fileContent, 'application/octet-stream')
                ->withHeaders(['Dropbox-API-Arg' => $apiArgs])
                ->post($this->dropboxContentUrl . '/files/upload');

            if ($response->successful()) {
                $fileData = $response->json();
                
                // Store file record in database
                $shootFile = ShootFile::create([
                    'shoot_id' => $shoot->id,
                    'filename' => $file->getClientOriginalName(),
                    'stored_filename' => $filename,
                    'path' => $dropboxPath,
                    'file_type' => $file->getMimeType(),
                    'file_size' => $file->getSize(),
                    'uploaded_by' => $userId,
                    'workflow_stage' => ShootFile::STAGE_TODO,
                    'dropbox_path' => $dropboxPath,
                    'dropbox_file_id' => $fileData['id'] ?? null
                ]);

                // Update shoot workflow status if this is the first photo upload
                if ($shoot->workflow_status === Shoot::WORKFLOW_BOOKED) {
                    $shoot->updateWorkflowStatus(Shoot::WORKFLOW_PHOTOS_UPLOADED, $userId);
                }

                Log::info("File uploaded to Dropbox ToDo folder", [
                    'shoot_id' => $shoot->id,
                    'filename' => $filename,
                    'path' => $dropboxPath
                ]);

                return $shootFile;
            } else {
                Log::error("Failed to upload file to Dropbox", $response->json() ?: []);
                throw new \Exception('Failed to upload file to Dropbox');
            }
        } catch (\Exception $e) {
            Log::error("Exception uploading file to Dropbox", ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    /**
     * Move file from ToDo to Completed folder
     */
    public function moveToCompleted(ShootFile $shootFile, $userId)
    {
        $shoot = $shootFile->shoot;
        
        // Determine the service category from the file's current path
        $serviceCategory = $this->getServiceCategoryFromPath($shootFile->dropbox_path, $shoot);
        
        $completedFolder = $shoot->dropboxFolders()
            ->where('folder_type', DropboxFolder::TYPE_COMPLETED)
            ->where('service_category', $serviceCategory)
            ->first();
        
        if (!$completedFolder) {
            throw new \Exception("Completed folder not found for category: {$serviceCategory}");
        }

        $newPath = $completedFolder->dropbox_path . '/' . $shootFile->stored_filename;

        try {
            $response = Http::withToken($this->getAccessToken())
                ->withOptions($this->httpOptions)
                ->post($this->dropboxApiUrl . '/files/move_v2', [
                    'from_path' => $shootFile->dropbox_path,
                    'to_path' => $newPath,
                    'allow_shared_folder' => false,
                    'autorename' => true
                ]);

            if ($response->successful()) {
                // Update file record
                $shootFile->dropbox_path = $newPath;
                $shootFile->moveToCompleted($userId);

                // Check if all files are moved to completed
                $todoFiles = $shoot->files()->where('workflow_stage', ShootFile::STAGE_TODO)->count();
                if ($todoFiles === 0 && $shoot->workflow_status === Shoot::WORKFLOW_PHOTOS_UPLOADED) {
                    $shoot->updateWorkflowStatus(Shoot::WORKFLOW_EDITING_COMPLETE, $userId);
                }

                Log::info("File moved to Completed folder", [
                    'shoot_id' => $shoot->id,
                    'filename' => $shootFile->filename,
                    'new_path' => $newPath
                ]);

                return true;
            } else {
                Log::error("Failed to move file in Dropbox", $response->json() ?: []);
                throw new \Exception('Failed to move file in Dropbox');
            }
        } catch (\Exception $e) {
            Log::error("Exception moving file in Dropbox", ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    /**
     * Copy verified files to server storage (keep in Dropbox)
     */
    public function moveToFinal(ShootFile $shootFile, $userId)
    {
        $shoot = $shootFile->shoot;

        try {
            // Download file from Dropbox and store on server (but keep in Dropbox)
            $this->downloadAndStoreOnServer($shootFile, $shootFile->dropbox_path);
            
            // Update file record - keep dropbox_path but mark as verified
            $shootFile->workflow_stage = ShootFile::STAGE_VERIFIED;
            $shootFile->save();

            Log::info("File copied to server storage (kept in Dropbox)", [
                'shoot_id' => $shoot->id,
                'filename' => $shootFile->filename,
                'dropbox_path' => $shootFile->dropbox_path,
                'server_path' => $shootFile->path
            ]);

            return true;
        } catch (\Exception $e) {
            Log::error("Exception copying file to server storage", ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    /**
     * Download file from Dropbox and store on server
     */
    protected function downloadAndStoreOnServer(ShootFile $shootFile, $dropboxPath)
    {
        try {
            $apiArgs = json_encode(['path' => $dropboxPath]);

            $response = Http::withToken($this->getAccessToken())
                ->withOptions($this->httpOptions)
                ->withHeaders(['Dropbox-API-Arg' => $apiArgs])
                ->get($this->dropboxContentUrl . '/files/download');

            if ($response->successful()) {
                $serverPath = "shoots/{$shootFile->shoot_id}/final/{$shootFile->stored_filename}";
                
                // Store file on server
                \Storage::disk('public')->put($serverPath, $response->body());
                
                // Update file path to server location
                $shootFile->path = $serverPath;
                $shootFile->save();

                Log::info("File downloaded and stored on server", [
                    'dropbox_path' => $dropboxPath,
                    'server_path' => $serverPath
                ]);
            } else {
                Log::error("Failed to download file from Dropbox", $response->json() ?: []);
                throw new \Exception('Failed to download file from Dropbox');
            }
        } catch (\Exception $e) {
            Log::error("Exception downloading file from Dropbox", ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    /**
     * List files in a specific folder
     */
    public function listFolderFiles($folderPath)
    {
        try {
            $response = Http::withToken($this->getAccessToken())
                ->withOptions($this->httpOptions)
                ->post($this->dropboxApiUrl . '/files/list_folder', [
                    'path' => $folderPath,
                    'recursive' => false,
                    'include_media_info' => true,
                ]);

            if ($response->successful()) {
                return $response->json();
            } else {
                Log::error("Failed to list Dropbox folder files", $response->json() ?: []);
                return null;
            }
        } catch (\Exception $e) {
            Log::error("Exception listing Dropbox folder files", ['error' => $e->getMessage()]);
            return null;
        }
    }

    /**
     * Generate address-based folder name
     */
    private function generateAddressFolderName(Shoot $shoot)
    {
        // Clean and format address for folder name
        $address = $shoot->address;
        $city = $shoot->city;
        $state = $shoot->state;
        
        // Remove special characters and replace spaces with hyphens
        $cleanAddress = preg_replace('/[^a-zA-Z0-9\s\-]/', '', $address);
        $cleanCity = preg_replace('/[^a-zA-Z0-9\s\-]/', '', $city);
        $cleanState = preg_replace('/[^a-zA-Z0-9\s\-]/', '', $state);
        
        // Replace spaces with hyphens and remove multiple hyphens
        $addressPart = preg_replace('/\s+/', '-', trim($cleanAddress));
        $cityPart = preg_replace('/\s+/', '-', trim($cleanCity));
        $statePart = preg_replace('/\s+/', '-', trim($cleanState));
        
        // Combine parts
        $folderName = "{$addressPart}-{$cityPart}-{$statePart}";
        
        // Clean up multiple hyphens and ensure it's not too long
        $folderName = preg_replace('/-+/', '-', $folderName);
        $folderName = substr($folderName, 0, 100); // Limit length
        
        return trim($folderName, '-');
    }

    /**
     * Get service categories based on the shoot's service
     */
    private function getServiceCategories(Shoot $shoot)
    {
        // If service_category is set, use it
        if ($shoot->service_category) {
            return [$shoot->service_category];
        }
        
        // Otherwise, determine from service name
        $serviceName = strtolower($shoot->service->name ?? '');
        
        if (strpos($serviceName, 'iguide') !== false) {
            return ['iGuide'];
        } elseif (strpos($serviceName, 'video') !== false) {
            return ['Video'];
        } else {
            // Default to Photos, but you might want to create all three
            return ['P']; // or return ['P', 'iGuide', 'Video'] to create all
        }
    }

    /**
     * Get category prefix for folder naming
     */
    private function getCategoryPrefix($category)
    {
        switch ($category) {
            case 'P':
                return 'P';
            case 'iGuide':
                return 'iGuide';
            case 'Video':
                return 'Video';
            default:
                return 'P';
        }
    }

    /**
     * Create folder if it doesn't exist
     */
    private function createFolderIfNotExists($path)
    {
        try {
            $response = Http::withToken($this->getAccessToken())
                ->withOptions($this->httpOptions)
                ->withHeaders(['Content-Type' => 'application/json'])
                ->withBody(json_encode(['path' => $path, 'autorename' => false]))
                ->post($this->dropboxApiUrl . '/files/create_folder_v2');

            if ($response->successful()) {
                Log::info("Created Dropbox folder: {$path}");
                return true;
            } else {
                $error = $response->json();
                // Check if folder already exists
                if (isset($error['error']['.tag']) && $error['error']['.tag'] === 'path' && 
                    isset($error['error']['path']['.tag']) && $error['error']['path']['.tag'] === 'conflict') {
                    Log::info("Dropbox folder already exists: {$path}");
                    return true;
                } else {
                    Log::error("Failed to create Dropbox folder: {$path}", $error ?: []);
                    return false;
                }
            }
        } catch (\Exception $e) {
            Log::error("Exception creating Dropbox folder: {$path}", ['error' => $e->getMessage()]);
            return false;
        }
    }

    /**
     * Get service category from file path
     */
    private function getServiceCategoryFromPath($path, $shoot)
    {
        // Extract category from path like "/RealEstatePhotos/ToDo/2025-01-18/P-123-Main-Street-Anytown-ST/file.jpg"
        if (strpos($path, '/P-') !== false) {
            return 'P';
        } elseif (strpos($path, '/iGuide-') !== false) {
            return 'iGuide';
        } elseif (strpos($path, '/Video-') !== false) {
            return 'Video';
        }
        
        // Fallback to shoot's service category or default
        return $shoot->service_category ?? 'P';
    }

    /**
     * Upload file directly to Completed folder (for edited files)
     */
    public function uploadToCompleted(Shoot $shoot, UploadedFile $file, $userId, $serviceCategory = null)
    {
        // Determine service category if not provided
        if (!$serviceCategory) {
            $serviceCategories = $this->getServiceCategories($shoot);
            $serviceCategory = $serviceCategories[0];
        }
        
        $completedFolder = $shoot->dropboxFolders()
            ->where('folder_type', DropboxFolder::TYPE_COMPLETED)
            ->where('service_category', $serviceCategory)
            ->first();
        
        if (!$completedFolder) {
            $this->createShootFolders($shoot);
            $completedFolder = $shoot->dropboxFolders()
                ->where('folder_type', DropboxFolder::TYPE_COMPLETED)
                ->where('service_category', $serviceCategory)
                ->first();
        }

        if (!$completedFolder) {
            throw new \Exception("Completed folder not found for category: {$serviceCategory}");
        }

        $filename = 'COMPLETED_' . time() . '_' . $file->getClientOriginalName();
        $dropboxPath = $completedFolder->dropbox_path . '/' . $filename;

        try {
            $fileContent = $file->get();
            
            $apiArgs = json_encode([
                'path' => $dropboxPath,
                'mode' => 'add',
                'autorename' => true,
                'mute' => false,
            ]);

            $response = Http::withToken($this->getAccessToken())
                ->withOptions($this->httpOptions)
                ->withBody($fileContent, 'application/octet-stream')
                ->withHeaders(['Dropbox-API-Arg' => $apiArgs])
                ->post($this->dropboxContentUrl . '/files/upload');

            if ($response->successful()) {
                $fileData = $response->json();
                
                // Store file record in database
                $shootFile = ShootFile::create([
                    'shoot_id' => $shoot->id,
                    'filename' => $file->getClientOriginalName(),
                    'stored_filename' => $filename,
                    'path' => $dropboxPath,
                    'file_type' => $file->getMimeType(),
                    'file_size' => $file->getSize(),
                    'uploaded_by' => $userId,
                    'workflow_stage' => ShootFile::STAGE_COMPLETED, // Directly to completed
                    'dropbox_path' => $dropboxPath,
                    'dropbox_file_id' => $fileData['id'] ?? null
                ]);

                // Update shoot workflow status if needed
                if ($shoot->workflow_status === Shoot::WORKFLOW_BOOKED) {
                    $shoot->updateWorkflowStatus(Shoot::WORKFLOW_EDITING_COMPLETE, $userId);
                }

                Log::info("File uploaded directly to Dropbox Completed folder", [
                    'shoot_id' => $shoot->id,
                    'filename' => $filename,
                    'path' => $dropboxPath
                ]);

                return $shootFile;
            } else {
                Log::error("Failed to upload file to Dropbox Completed folder", $response->json() ?: []);
                throw new \Exception('Failed to upload file to Dropbox');
            }
        } catch (\Exception $e) {
            Log::error("Exception uploading file to Dropbox Completed folder", ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    /**
     * Copy file from user's Dropbox to ToDo folder
     */
    public function copyFromDropboxToTodo(Shoot $shoot, $sourcePath, $filename, $userId, $serviceCategory = null)
    {
        // Determine service category if not provided
        if (!$serviceCategory) {
            $serviceCategories = $this->getServiceCategories($shoot);
            $serviceCategory = $serviceCategories[0];
        }
        
        $todoFolder = $shoot->dropboxFolders()
            ->where('folder_type', DropboxFolder::TYPE_TODO)
            ->where('service_category', $serviceCategory)
            ->first();
        
        if (!$todoFolder) {
            $this->createShootFolders($shoot);
            $todoFolder = $shoot->dropboxFolders()
                ->where('folder_type', DropboxFolder::TYPE_TODO)
                ->where('service_category', $serviceCategory)
                ->first();
        }

        if (!$todoFolder) {
            throw new \Exception("ToDo folder not found for category: {$serviceCategory}");
        }

        $newFilename = 'COPIED_TODO_' . time() . '_' . $filename;
        $destinationPath = $todoFolder->dropbox_path . '/' . $newFilename;

        try {
            // Copy file within Dropbox
            $response = Http::withToken($this->getAccessToken())
                ->withOptions($this->httpOptions)
                ->withHeaders(['Content-Type' => 'application/json'])
                ->withBody(json_encode([
                    'from_path' => $sourcePath,
                    'to_path' => $destinationPath,
                    'allow_shared_folder' => false,
                    'autorename' => true
                ]))
                ->post($this->dropboxApiUrl . '/files/copy_v2');

            if ($response->successful()) {
                $fileData = $response->json();
                
                // Get file metadata to determine size and type
                $metadataResponse = Http::withToken($this->getAccessToken())
                    ->withOptions($this->httpOptions)
                    ->withHeaders(['Content-Type' => 'application/json'])
                    ->withBody(json_encode(['path' => $destinationPath]))
                    ->post($this->dropboxApiUrl . '/files/get_metadata');

                $fileSize = 0;
                $mimeType = 'application/octet-stream';
                
                if ($metadataResponse->successful()) {
                    $metadata = $metadataResponse->json();
                    $fileSize = $metadata['size'] ?? 0;
                    $mimeType = $this->getMimeTypeFromExtension($filename);
                }
                
                // Store file record in database
                $shootFile = ShootFile::create([
                    'shoot_id' => $shoot->id,
                    'filename' => $filename,
                    'stored_filename' => $newFilename,
                    'path' => $destinationPath,
                    'file_type' => $mimeType,
                    'file_size' => $fileSize,
                    'uploaded_by' => $userId,
                    'workflow_stage' => ShootFile::STAGE_TODO,
                    'dropbox_path' => $destinationPath,
                    'dropbox_file_id' => $fileData['id'] ?? null
                ]);

                // Update shoot workflow status if this is the first photo upload
                if ($shoot->workflow_status === Shoot::WORKFLOW_BOOKED) {
                    $shoot->updateWorkflowStatus(Shoot::WORKFLOW_PHOTOS_UPLOADED, $userId);
                }

                Log::info("File copied from Dropbox to ToDo folder", [
                    'shoot_id' => $shoot->id,
                    'source_path' => $sourcePath,
                    'destination_path' => $destinationPath,
                    'filename' => $filename
                ]);

                return $shootFile;
            } else {
                Log::error("Failed to copy file in Dropbox", $response->json() ?: []);
                throw new \Exception('Failed to copy file in Dropbox');
            }
        } catch (\Exception $e) {
            Log::error("Exception copying file in Dropbox", ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    /**
     * Get MIME type from file extension
     */
    private function getMimeTypeFromExtension($filename)
    {
        $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        
        $mimeTypes = [
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'gif' => 'image/gif',
            'bmp' => 'image/bmp',
            'tiff' => 'image/tiff',
            'raw' => 'image/x-canon-raw',
            'cr2' => 'image/x-canon-cr2',
            'nef' => 'image/x-nikon-nef',
            'arw' => 'image/x-sony-arw',
            'mp4' => 'video/mp4',
            'mov' => 'video/quicktime',
            'avi' => 'video/x-msvideo'
        ];

        return $mimeTypes[$extension] ?? 'application/octet-stream';
    }

    /**
     * Delete file from Dropbox
     */
    private function deleteFromDropbox($path)
    {
        try {
            $response = Http::withToken($this->getAccessToken())
                ->withOptions($this->httpOptions)
                ->withHeaders(['Content-Type' => 'application/json'])
                ->withBody(json_encode(['path' => $path]))
                ->post($this->dropboxApiUrl . '/files/delete_v2');

            if ($response->successful()) {
                Log::info("Deleted file from Dropbox: {$path}");
                return true;
            } else {
                Log::error("Failed to delete file from Dropbox: {$path}", $response->json() ?: []);
                return false;
            }
        } catch (\Exception $e) {
            Log::error("Exception deleting file from Dropbox: {$path}", ['error' => $e->getMessage()]);
            return false;
        }
    }
}

<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TestDropboxController extends Controller
{
    public function testConnection()
    {
        $accessToken = config('services.dropbox.access_token');
        
        if (!$accessToken) {
            return response()->json([
                'success' => false,
                'message' => 'Dropbox access token not configured'
            ]);
        }

        try {
            // Test by getting current user account info
            $response = Http::withToken($accessToken)
                ->withOptions([
                    'verify' => false, // Disable SSL verification for development
                ])
                ->withHeaders([
                    'Content-Type' => 'application/json'
                ])
                ->withBody('null')
                ->post('https://api.dropboxapi.com/2/users/get_current_account');

            if ($response->successful()) {
                $userData = $response->json();
                return response()->json([
                    'success' => true,
                    'message' => 'Dropbox connection successful!',
                    'account_info' => [
                        'name' => $userData['name']['display_name'] ?? 'Unknown',
                        'email' => $userData['email'] ?? 'Unknown',
                        'account_id' => $userData['account_id'] ?? 'Unknown',
                        'account_type' => $userData['account_type']['.tag'] ?? 'Unknown'
                    ],
                    'storage_location' => 'Files will be uploaded to this Dropbox account ☝️'
                ]);
            } else {
                Log::error('Dropbox API Error Details', [
                    'status' => $response->status(),
                    'headers' => $response->headers(),
                    'body' => $response->body(),
                    'json' => $response->json()
                ]);
                
                return response()->json([
                    'success' => false,
                    'message' => 'Dropbox API error',
                    'status_code' => $response->status(),
                    'error_body' => $response->body(),
                    'error_json' => $response->json(),
                    'access_token_length' => strlen($accessToken ?? ''),
                    'access_token_starts_with' => substr($accessToken ?? '', 0, 10) . '...'
                ]);
            }
        } catch (\Exception $e) {
            Log::error('Dropbox test connection failed: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Connection failed: ' . $e->getMessage()
            ]);
        }
    }

    public function debugConfig()
    {
        return response()->json([
            'dropbox_client_id' => config('services.dropbox.client_id'),
            'dropbox_client_secret_length' => strlen(config('services.dropbox.client_secret') ?? ''),
            'dropbox_access_token_length' => strlen(config('services.dropbox.access_token') ?? ''),
            'dropbox_access_token_starts_with' => substr(config('services.dropbox.access_token') ?? '', 0, 10) . '...',
            'app_env' => config('app.env'),
            'all_dropbox_config' => config('services.dropbox')
        ]);
    }

    public function testWithCurl()
    {
        $accessToken = config('services.dropbox.access_token');
        
        if (!$accessToken) {
            return response()->json([
                'success' => false,
                'message' => 'Dropbox access token not configured'
            ]);
        }

        // Test with raw cURL
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, 'https://api.dropboxapi.com/2/users/get_current_account');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, 'null');
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $accessToken,
            'Content-Type: application/json'
        ]);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        return response()->json([
            'curl_error' => $error,
            'http_code' => $httpCode,
            'response' => $response,
            'response_decoded' => json_decode($response, true)
        ]);
    }

    public function testFolderStructure()
    {
        try {
            // Create a proper Shoot model instance for testing
            $testShoot = new \App\Models\Shoot();
            $testShoot->id = 999;
            $testShoot->scheduled_date = now();
            $testShoot->address = '123 Main Street APT 456';
            $testShoot->city = 'Anytown';
            $testShoot->state = 'ST';
            $testShoot->service_category = 'P';
            
            // Create a mock service for testing
            $testService = new \App\Models\Service();
            $testService->name = 'Real Estate Photography';
            $testShoot->setRelation('service', $testService);

            $dropboxService = new \App\Services\DropboxWorkflowService();
            
            // Test address folder name generation
            $reflection = new \ReflectionClass($dropboxService);
            $method = $reflection->getMethod('generateAddressFolderName');
            $method->setAccessible(true);
            $addressFolder = $method->invoke($dropboxService, $testShoot);

            // Test service categories
            $categoriesMethod = $reflection->getMethod('getServiceCategories');
            $categoriesMethod->setAccessible(true);
            $categories = $categoriesMethod->invoke($dropboxService, $testShoot);

            // Test category prefix
            $prefixMethod = $reflection->getMethod('getCategoryPrefix');
            $prefixMethod->setAccessible(true);
            $prefixes = [];
            foreach ($categories as $cat) {
                $prefixes[$cat] = $prefixMethod->invoke($dropboxService, $cat);
            }

            return response()->json([
                'success' => true,
                'test_shoot_data' => [
                    'address' => $testShoot->address,
                    'city' => $testShoot->city,
                    'state' => $testShoot->state,
                    'service_category' => $testShoot->service_category,
                    'date' => $testShoot->scheduled_date->format('Y-m-d')
                ],
                'generated_address_folder' => $addressFolder,
                'service_categories' => $categories,
                'category_prefixes' => $prefixes,
                'expected_folder_structure' => [
                    'base_path' => '/RealEstatePhotos',
                    'todo_folders' => array_map(function($cat) use ($addressFolder, $testShoot) {
                        $prefix = $cat === 'P' ? 'P' : $cat;
                        return "/RealEstatePhotos/ToDo/" . $testShoot->scheduled_date->format('Y-m-d') . "/{$prefix}-{$addressFolder}";
                    }, $categories),
                    'completed_folders' => array_map(function($cat) use ($addressFolder, $testShoot) {
                        $prefix = $cat === 'P' ? 'P' : $cat;
                        return "/RealEstatePhotos/Completed/" . $testShoot->scheduled_date->format('Y-m-d') . "/{$prefix}-{$addressFolder}";
                    }, $categories),
                    'final_storage' => 'Files copied to server storage AND kept in Dropbox after verification'
                ],
                'examples' => [
                    'P-123-Main-Street-APT123-Anytown-ST' => 'Photos',
                    'iGuide-123-Main-Street-Anytown-ST' => 'iGuide',
                    'Video-123-Main-Street-Anytown-ST' => 'Videos'
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }
    }

    public function testFolderCreation()
    {
        try {
            // Create a test shoot
            $testShoot = new \App\Models\Shoot();
            $testShoot->id = 999;
            $testShoot->scheduled_date = now();
            $testShoot->address = '123 Main Street APT 456';
            $testShoot->city = 'Anytown';
            $testShoot->state = 'ST';
            $testShoot->service_category = 'P';
            
            // Create a mock service
            $testService = new \App\Models\Service();
            $testService->name = 'Real Estate Photography';
            $testShoot->setRelation('service', $testService);

            $dropboxService = new \App\Services\DropboxWorkflowService();
            
            // Test creating the actual folder structure
            $dropboxService->createShootFolders($testShoot);
            
            return response()->json([
                'success' => true,
                'message' => 'Test folder structure created successfully with test markers!',
                'shoot_data' => [
                    'address' => $testShoot->address,
                    'city' => $testShoot->city,
                    'state' => $testShoot->state,
                    'date' => $testShoot->scheduled_date->format('Y-m-d')
                ],
                'expected_folders' => [
                    'todo' => "/RealEstatePhotos/ToDoTest1/{$testShoot->scheduled_date->format('Y-m-d')}/P-123-Main-Street-APT-456-Anytown-ST",
                    'completed' => "/RealEstatePhotos/CompletedTest1/{$testShoot->scheduled_date->format('Y-m-d')}/P-123-Main-Street-APT-456-Anytown-ST"
                ],
                'file_naming' => [
                    'raw_files' => 'TODO_timestamp_filename.jpg',
                    'edited_files' => 'COMPLETED_timestamp_filename.jpg',
                    'copied_files' => 'COPIED_TODO_timestamp_filename.jpg'
                ],
                'note' => 'Check your Dropbox account for the created test folders with markers!'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Folder creation failed: ' . $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }
    }

    public function createTestShoot()
    {
        try {
            // Get the first user and service for testing
            $user = \App\Models\User::first();
            $service = \App\Models\Service::first();

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'No users found. Please create a user first.'
                ]);
            }

            if (!$service) {
                return response()->json([
                    'success' => false,
                    'message' => 'No services found. Please create a service first.'
                ]);
            }

            // Create test shoot data
            $shootData = [
                'client_id' => $user->id,
                'photographer_id' => $user->id,
                'service_id' => $service->id,
                'address' => '123 Test Street APT 456',
                'city' => 'Test City',
                'state' => 'TS',
                'zip' => '12345',
                'scheduled_date' => now()->addDays(1)->format('Y-m-d'),
                'time' => '10:00 AM',
                'base_quote' => 500.00,
                'tax_amount' => 50.00,
                'total_quote' => 550.00,
                'payment_status' => 'unpaid',
                'status' => 'booked',
                'created_by' => 'Test System',
                'service_category' => 'P'
            ];

            $shoot = \App\Models\Shoot::create($shootData);

            // Create Dropbox folders
            $dropboxService = new \App\Services\DropboxWorkflowService();
            $dropboxService->createShootFolders($shoot);

            return response()->json([
                'success' => true,
                'message' => 'Test shoot created successfully!',
                'shoot' => [
                    'id' => $shoot->id,
                    'address' => $shoot->address,
                    'city' => $shoot->city,
                    'state' => $shoot->state,
                    'scheduled_date' => $shoot->scheduled_date,
                    'status' => $shoot->status,
                    'workflow_status' => $shoot->workflow_status
                ],
                'test_instructions' => [
                    'step_1' => 'Go to your frontend and find this shoot',
                    'step_2' => 'Try uploading files using Raw/Unedited Files button',
                    'step_3' => 'Try uploading files using Edited/Final Files button',
                    'step_4' => 'Check your Dropbox for ToDoTest1 and CompletedTest1 folders'
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create test shoot: ' . $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }
    }

    public function createTestShootViaAPI()
    {
        try {
            // Get the first user and service for testing
            $user = \App\Models\User::first();
            $service = \App\Models\Service::first();

            if (!$user || !$service) {
                return response()->json([
                    'success' => false,
                    'message' => 'Missing user or service data'
                ]);
            }

            // Use the ShootController to create the shoot (tests the actual API)
            $shootController = new \App\Http\Controllers\API\ShootController(new \App\Services\DropboxWorkflowService());
            
            $request = new \Illuminate\Http\Request([
                'client_id' => $user->id,
                'photographer_id' => $user->id,
                'service_id' => $service->id,
                'address' => '123 Test Street APT 456',
                'city' => 'Test City',
                'state' => 'TS',
                'zip' => '12345',
                'scheduled_date' => now()->addDays(1)->format('Y-m-d'),
                'time' => '10:00 AM',
                'base_quote' => 500.00,
                'tax_amount' => 50.00,
                'total_quote' => 550.00,
                'payment_status' => 'unpaid',
                'status' => 'booked',
                'created_by' => 'Test System',
                'service_category' => 'P'
            ]);

            // Set the authenticated user for the request
            $request->setUserResolver(function () use ($user) {
                return $user;
            });

            $response = $shootController->store($request);
            
            return response()->json([
                'success' => true,
                'message' => 'Test shoot created via API successfully!',
                'api_response' => $response->getData(),
                'test_instructions' => [
                    'step_1' => 'Check your frontend shoots page',
                    'step_2' => 'Look for the test shoot in the list',
                    'step_3' => 'Try uploading files to test the workflow'
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'API test failed: ' . $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }
    }

    public function setupLongLivedToken()
    {
        $clientId = config('services.dropbox.client_id');
        $redirectUri = config('services.dropbox.redirect');
        
        // Create authorization URL for long-lived tokens
        $authUrl = "https://www.dropbox.com/oauth2/authorize?" . http_build_query([
            'client_id' => $clientId,
            'response_type' => 'code',
            'redirect_uri' => $redirectUri,
            'token_access_type' => 'offline', // This requests a refresh token
            'scope' => 'files.content.write files.content.read files.metadata.write files.metadata.read'
        ]);

        return response()->json([
            'message' => 'To get long-lived tokens that never expire, follow these steps:',
            'steps' => [
                '1. Visit the authorization URL below',
                '2. Authorize your app',
                '3. Copy the authorization code from the callback',
                '4. Use the /api/dropbox/exchange-code endpoint with the code'
            ],
            'authorization_url' => $authUrl,
            'callback_url' => $redirectUri,
            'note' => 'After authorization, you will get both access_token and refresh_token that can be used indefinitely'
        ]);
    }
}
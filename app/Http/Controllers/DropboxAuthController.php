<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;

class DropboxAuthController extends Controller
{
    /**
     * Dropbox Webhook endpoint
     * Handles verification (GET) and notifications (POST).
     */
    public function webhook(Request $request)
    {
        // 1. Verification step (GET)
        if ($request->isMethod('get')) {
            $challenge = $request->query('challenge');
            if ($challenge) {
                return response($challenge, 200)
                    ->header('Content-Type', 'text/plain');
            }
        }

        // 2. Notification step (POST)
        if ($request->isMethod('post')) {
            $payload = $request->getContent();
            Log::info('Dropbox Webhook Payload:', [$payload]);

            // Dropbox sends account_ids that changed
            $data = json_decode($payload, true);
            if (isset($data['list_folder']['accounts'])) {
                foreach ($data['list_folder']['accounts'] as $accountId) {
                    Log::info("Dropbox change detected for account: " . $accountId);

                    // Example: Fetch latest changes using Dropbox API
                    // You would need to store access_token per user earlier
                    // $accessToken = ... get from DB ...
                    // $this->fetchLatestChanges($accessToken, $accountId);
                }
            }

            return response()->json(['status' => 'ok'], 200);
        }

        return response()->json(['error' => 'Invalid request'], 400);
    }

    /**
     * Fetch latest changes from Dropbox after webhook trigger
     */
    private function fetchLatestChanges($accessToken, $accountId)
    {
        try {
            // Call Dropbox API: /2/files/list_folder/continue with stored cursor
            $cursor = null; // load from DB for user

            $endpoint = $cursor
                ? 'https://api.dropboxapi.com/2/files/list_folder/continue'
                : 'https://api.dropboxapi.com/2/files/list_folder';

            $body = $cursor
                ? ['cursor' => $cursor]
                : ['path' => '', 'recursive' => true];

            $response = Http::withToken($accessToken)
                ->post($endpoint, $body);

            if ($response->successful()) {
                $changes = $response->json();
                Log::info("Fetched changes for $accountId", $changes);

                // Update cursor in DB for next time
                // DB::table('dropbox_users')->where('account_id', $accountId)->update(['cursor' => $changes['cursor']]);

                // Process new/updated/deleted files
            } else {
                Log::error("Dropbox API error: " . $response->body());
            }
        } catch (\Exception $e) {
            Log::error("Error fetching Dropbox changes: " . $e->getMessage());
        }
    }

    // (Already added before in your routes file)
    public function exchangeToken(Request $request) {}
    public function refreshToken(Request $request) {}
    public function revokeToken(Request $request) {}
}

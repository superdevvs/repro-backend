<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use App\Models\OauthToken;

class DropboxTokenService
{
    protected $clientId;
    protected $clientSecret;
    
    public function __construct()
    {
        $this->clientId = config('services.dropbox.client_id');
        $this->clientSecret = config('services.dropbox.client_secret');
    }

    /**
     * Get a valid access token, refreshing if necessary
     */
    public function getValidAccessToken()
    {
        // Prefer DB-stored token
        $record = OauthToken::where('provider', 'dropbox')->first();
        $accessToken = $record?->access_token ?: config('services.dropbox.access_token');
        
        // Check if token is still valid
        if ($this->isTokenValid($accessToken)) {
            return $accessToken;
        }

        // Try to refresh the token
        $refreshToken = $record?->refresh_token ?: config('services.dropbox.refresh_token');
        if ($refreshToken) {
            $newToken = $this->refreshAccessToken($refreshToken);
            if ($newToken) {
                return $newToken;
            }
        }

        // If refresh fails, throw exception
        throw new \Exception('Dropbox access token expired and could not be refreshed. Please re-authenticate.');
    }

    /**
     * Check if access token is still valid
     */
    public function isTokenValid($accessToken)
    {
        if (!$accessToken) {
            return false;
        }

        // Cache the token validity check for 5 minutes to avoid too many API calls
        $cacheKey = 'dropbox_token_valid_' . substr($accessToken, 0, 10);
        
        return Cache::remember($cacheKey, 300, function () use ($accessToken) {
            try {
                $response = Http::withToken($accessToken)
                    ->withOptions(['verify' => config('app.env') === 'production'])
                    ->withHeaders(['Content-Type' => 'application/json'])
                    ->withBody('null')
                    ->post('https://api.dropboxapi.com/2/users/get_current_account');

                return $response->successful();
            } catch (\Exception $e) {
                Log::warning('Token validation failed', ['error' => $e->getMessage()]);
                return false;
            }
        });
    }

    /**
     * Refresh access token using refresh token
     */
    public function refreshAccessToken($refreshToken)
    {
        try {
            $response = Http::withBasicAuth($this->clientId, $this->clientSecret)
                ->asForm()
                ->post('https://api.dropboxapi.com/oauth2/token', [
                    'grant_type' => 'refresh_token',
                    'refresh_token' => $refreshToken,
                ]);

            if ($response->successful()) {
                $tokenData = $response->json();
                $newAccessToken = $tokenData['access_token'];
                
                // Update the environment configuration (in memory)
                config(['services.dropbox.access_token' => $newAccessToken]);
                
                // Optionally update refresh token if provided
                if (isset($tokenData['refresh_token'])) {
                    config(['services.dropbox.refresh_token' => $tokenData['refresh_token']]);
                }

                // Clear the token validity cache
                Cache::forget('dropbox_token_valid_' . substr($newAccessToken, 0, 10));

                Log::info('Dropbox access token refreshed successfully');

                // Persist to DB so it survives restarts
                OauthToken::updateOrCreate(
                    ['provider' => 'dropbox'],
                    [
                        'access_token' => $newAccessToken,
                        'refresh_token' => $tokenData['refresh_token'] ?? $refreshToken,
                        'expires_at' => isset($tokenData['expires_in']) ? now()->addSeconds((int)$tokenData['expires_in']) : null,
                    ]
                );
                
                return $newAccessToken;
            } else {
                Log::error('Failed to refresh Dropbox token', $response->json());
                return null;
            }
        } catch (\Exception $e) {
            Log::error('Exception refreshing Dropbox token', ['error' => $e->getMessage()]);
            return null;
        }
    }

    /**
     * Get initial tokens using authorization code (for setup)
     */
    public function exchangeCodeForTokens($authorizationCode)
    {
        try {
            $response = Http::withBasicAuth($this->clientId, $this->clientSecret)
                ->asForm()
                ->post('https://api.dropboxapi.com/oauth2/token', [
                    'code' => $authorizationCode,
                    'grant_type' => 'authorization_code',
                    'redirect_uri' => config('services.dropbox.redirect'),
                ]);

            if ($response->successful()) {
                $tokenData = $response->json();
                
                Log::info('Dropbox tokens obtained successfully', [
                    'has_access_token' => isset($tokenData['access_token']),
                    'has_refresh_token' => isset($tokenData['refresh_token']),
                    'expires_in' => $tokenData['expires_in'] ?? 'no_expiration'
                ]);
                
                return $tokenData;
            } else {
                Log::error('Failed to exchange code for tokens', $response->json());
                return null;
            }
        } catch (\Exception $e) {
            Log::error('Exception exchanging code for tokens', ['error' => $e->getMessage()]);
            return null;
        }
    }
}

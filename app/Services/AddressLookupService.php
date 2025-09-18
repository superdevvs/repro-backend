<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class AddressLookupService
{
    private $googleApiKey;
    private $baseUrl = 'https://maps.googleapis.com/maps/api';

    public function __construct()
    {
        $this->googleApiKey = config('services.google.places_api_key');
    }

    /**
     * Search for address suggestions using Google Places Autocomplete
     */
    public function searchAddresses(string $query, array $options = []): array
    {
        if (empty($this->googleApiKey)) {
            throw new \Exception('Google Places API key not configured');
        }

        if (strlen($query) < 3) {
            return [];
        }

        // Cache key for the search
        $cacheKey = 'address_search_' . md5($query . serialize($options));
        
        return Cache::remember($cacheKey, 300, function () use ($query, $options) {
            try {
                $params = [
                    'input' => $query,
                    'key' => $this->googleApiKey,
                    'types' => 'address',
                    'components' => 'country:us', // Restrict to US addresses
                ];

                // Add optional parameters
                if (isset($options['location'])) {
                    $params['location'] = $options['location'];
                }
                if (isset($options['radius'])) {
                    $params['radius'] = $options['radius'];
                }

                $response = Http::get($this->baseUrl . '/place/autocomplete/json', $params);

                if (!$response->successful()) {
                    Log::error('Google Places API error', [
                        'status' => $response->status(),
                        'body' => $response->body()
                    ]);
                    return [];
                }

                $data = $response->json();

                if ($data['status'] !== 'OK') {
                    Log::warning('Google Places API warning', [
                        'status' => $data['status'],
                        'error_message' => $data['error_message'] ?? 'Unknown error'
                    ]);
                    return [];
                }

                return $this->formatAddressSuggestions($data['predictions']);

            } catch (\Exception $e) {
                Log::error('Address lookup failed', [
                    'query' => $query,
                    'error' => $e->getMessage()
                ]);
                return [];
            }
        });
    }

    /**
     * Get detailed address information by place ID
     */
    public function getAddressDetails(string $placeId): ?array
    {
        if (empty($this->googleApiKey)) {
            throw new \Exception('Google Places API key not configured');
        }

        $cacheKey = 'address_details_' . $placeId;
        
        return Cache::remember($cacheKey, 3600, function () use ($placeId) {
            try {
                $params = [
                    'place_id' => $placeId,
                    'key' => $this->googleApiKey,
                    'fields' => 'address_components,formatted_address,geometry,name,types'
                ];

                $response = Http::get($this->baseUrl . '/place/details/json', $params);

                if (!$response->successful()) {
                    Log::error('Google Places Details API error', [
                        'place_id' => $placeId,
                        'status' => $response->status(),
                        'body' => $response->body()
                    ]);
                    return null;
                }

                $data = $response->json();

                if ($data['status'] !== 'OK') {
                    Log::warning('Google Places Details API warning', [
                        'place_id' => $placeId,
                        'status' => $data['status'],
                        'error_message' => $data['error_message'] ?? 'Unknown error'
                    ]);
                    return null;
                }

                return $this->parseAddressComponents($data['result']);

            } catch (\Exception $e) {
                Log::error('Address details lookup failed', [
                    'place_id' => $placeId,
                    'error' => $e->getMessage()
                ]);
                return null;
            }
        });
    }

    /**
     * Validate and standardize an address
     */
    public function validateAddress(array $addressData): array
    {
        $validation = [
            'is_valid' => true,
            'errors' => [],
            'suggestions' => [],
            'standardized' => $addressData
        ];

        // Required fields
        $required = ['address', 'city', 'state', 'zip'];
        foreach ($required as $field) {
            if (empty($addressData[$field])) {
                $validation['is_valid'] = false;
                $validation['errors'][] = "Missing required field: {$field}";
            }
        }

        // Validate ZIP code format
        if (!empty($addressData['zip'])) {
            if (!preg_match('/^\d{5}(-\d{4})?$/', $addressData['zip'])) {
                $validation['is_valid'] = false;
                $validation['errors'][] = 'Invalid ZIP code format';
            }
        }

        // Validate state (2-letter code)
        if (!empty($addressData['state'])) {
            if (strlen($addressData['state']) !== 2) {
                $validation['is_valid'] = false;
                $validation['errors'][] = 'State must be 2-letter code (e.g., CA, NY)';
            }
        }

        // If we have a place_id, get standardized address
        if (!empty($addressData['place_id'])) {
            $details = $this->getAddressDetails($addressData['place_id']);
            if ($details) {
                $validation['standardized'] = array_merge($addressData, $details);
            }
        }

        return $validation;
    }

    /**
     * Format address suggestions for frontend
     */
    private function formatAddressSuggestions(array $predictions): array
    {
        return array_map(function ($prediction) {
            return [
                'place_id' => $prediction['place_id'],
                'description' => $prediction['description'],
                'main_text' => $prediction['structured_formatting']['main_text'] ?? '',
                'secondary_text' => $prediction['structured_formatting']['secondary_text'] ?? '',
                'types' => $prediction['types'] ?? []
            ];
        }, $predictions);
    }

    /**
     * Parse Google Places address components into our format
     */
    private function parseAddressComponents(array $placeData): array
    {
        $components = $placeData['address_components'] ?? [];
        $parsed = [
            'formatted_address' => $placeData['formatted_address'] ?? '',
            'address' => '',
            'city' => '',
            'state' => '',
            'zip' => '',
            'country' => '',
            'latitude' => null,
            'longitude' => null
        ];

        // Extract coordinates
        if (isset($placeData['geometry']['location'])) {
            $parsed['latitude'] = $placeData['geometry']['location']['lat'];
            $parsed['longitude'] = $placeData['geometry']['location']['lng'];
        }

        // Parse address components
        foreach ($components as $component) {
            $types = $component['types'];
            $longName = $component['long_name'];
            $shortName = $component['short_name'];

            if (in_array('street_number', $types)) {
                $parsed['street_number'] = $longName;
            } elseif (in_array('route', $types)) {
                $parsed['street_name'] = $longName;
            } elseif (in_array('locality', $types)) {
                $parsed['city'] = $longName;
            } elseif (in_array('administrative_area_level_1', $types)) {
                $parsed['state'] = $shortName;
            } elseif (in_array('postal_code', $types)) {
                $parsed['zip'] = $longName;
            } elseif (in_array('country', $types)) {
                $parsed['country'] = $shortName;
            }
        }

        // Combine street number and name
        if (!empty($parsed['street_number']) && !empty($parsed['street_name'])) {
            $parsed['address'] = $parsed['street_number'] . ' ' . $parsed['street_name'];
        } elseif (!empty($parsed['street_name'])) {
            $parsed['address'] = $parsed['street_name'];
        }

        return $parsed;
    }

    /**
     * Get distance between two addresses
     */
    public function getDistance(array $origin, array $destination): ?array
    {
        if (empty($this->googleApiKey)) {
            throw new \Exception('Google Places API key not configured');
        }

        try {
            $originStr = $this->formatAddressForApi($origin);
            $destinationStr = $this->formatAddressForApi($destination);

            $params = [
                'origins' => $originStr,
                'destinations' => $destinationStr,
                'key' => $this->googleApiKey,
                'units' => 'imperial'
            ];

            $response = Http::get($this->baseUrl . '/distancematrix/json', $params);

            if (!$response->successful()) {
                return null;
            }

            $data = $response->json();

            if ($data['status'] !== 'OK' || empty($data['rows'][0]['elements'][0])) {
                return null;
            }

            $element = $data['rows'][0]['elements'][0];

            if ($element['status'] !== 'OK') {
                return null;
            }

            return [
                'distance' => $element['distance']['text'],
                'distance_value' => $element['distance']['value'], // in meters
                'duration' => $element['duration']['text'],
                'duration_value' => $element['duration']['value'] // in seconds
            ];

        } catch (\Exception $e) {
            Log::error('Distance calculation failed', [
                'origin' => $origin,
                'destination' => $destination,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * Format address array for API calls
     */
    private function formatAddressForApi(array $address): string
    {
        $parts = [];
        
        if (!empty($address['address'])) {
            $parts[] = $address['address'];
        }
        if (!empty($address['city'])) {
            $parts[] = $address['city'];
        }
        if (!empty($address['state'])) {
            $parts[] = $address['state'];
        }
        if (!empty($address['zip'])) {
            $parts[] = $address['zip'];
        }

        return implode(', ', $parts);
    }
}
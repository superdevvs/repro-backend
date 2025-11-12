<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class AddressLookupService
{
    private $provider;
    private $googleApiKey;
    private $googleBaseUrl = 'https://maps.googleapis.com/maps/api';

    private $locationIqKey;
    private $locationIqBaseUrl;

    public function __construct()
    {
        $this->provider = config('services.address.provider', 'locationiq');
        $this->googleApiKey = config('services.google.places_api_key');

        $this->locationIqKey = config('services.locationiq.key');
        $this->locationIqBaseUrl = rtrim(config('services.locationiq.base_url', 'https://us1.locationiq.com/v1'), '/');
    }

    /**
     * Search for address suggestions using Google Places Autocomplete
     */
    public function searchAddresses(string $query, array $options = []): array
    {
        if (strlen($query) < 3) {
            return [];
        }

        $cacheKey = 'address_search_' . md5($this->provider . '|' . $query . serialize($options));

        return Cache::remember($cacheKey, 300, function () use ($query, $options) {
            try {
                if ($this->provider === 'locationiq') {
                    if (empty($this->locationIqKey)) {
                        throw new \Exception('LocationIQ API key not configured');
                    }

                    // Cleanly call LocationIQ logic
                    return $this->locationIqAutocomplete($query, $options);
                }

                // Default to Google
                if (empty($this->googleApiKey)) {
                    throw new \Exception('Google Places API key not configured');
                }

                return $this->googleAutocomplete($query, $options);

            } catch (\Exception $e) {
                Log::error('Address lookup failed', [
                    'provider' => $this->provider,
                    'query' => $query,
                    'error' => $e->getMessage(),
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
        $cacheKey = 'address_details_' . $this->provider . '_' . $placeId;
        return Cache::remember($cacheKey, 3600, function () use ($placeId) {
            try {
                if ($this->provider === 'locationiq') {
                    if (empty($this->locationIqKey)) {
                        throw new \Exception('LocationIQ API key not configured');
                    }
                    return $this->locationIqDetails($placeId);
                }

                if (empty($this->googleApiKey)) {
                    throw new \Exception('Google Places API key not configured');
                }
                return $this->googleDetails($placeId);
            } catch (\Exception $e) {
                Log::error('Address details lookup failed', [
                    'provider' => $this->provider,
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

            $response = Http::get($this->googleBaseUrl . '/distancematrix/json', $params);

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
            // Fallback: approximate great-circle distance (no routing)
            return $this->approxDistanceByCoordinates($origin, $destination);
        }
    }

    private function googleAutocomplete(string $query, array $options): array
    {
        $params = [
            'input' => $query,
            'key' => $this->googleApiKey,
            'types' => 'address',
            'components' => 'country:us',
        ];
        if (isset($options['location'])) $params['location'] = $options['location'];
        if (isset($options['radius'])) $params['radius'] = $options['radius'];

        $response = Http::get($this->googleBaseUrl . '/place/autocomplete/json', $params);
        if (!$response->successful()) {
            Log::error('Google Places API error', ['status' => $response->status(), 'body' => $response->body()]);
            return [];
        }
        $data = $response->json();
        if (($data['status'] ?? '') !== 'OK') {
            Log::warning('Google Places API warning', ['status' => $data['status'] ?? 'unknown', 'error_message' => $data['error_message'] ?? null]);
            return [];
        }
        return $this->formatAddressSuggestions($data['predictions'] ?? []);
    }

    private function googleDetails(string $placeId): ?array
    {
        $params = [
            'place_id' => $placeId,
            'key' => $this->googleApiKey,
            'fields' => 'address_components,formatted_address,geometry,name,types'
        ];
        $response = Http::get($this->googleBaseUrl . '/place/details/json', $params);
        if (!$response->successful()) {
            Log::error('Google Places Details API error', ['place_id' => $placeId, 'status' => $response->status(), 'body' => $response->body()]);
            return null;
        }
        $data = $response->json();
        if (($data['status'] ?? '') !== 'OK') {
            Log::warning('Google Places Details API warning', ['place_id' => $placeId, 'status' => $data['status'] ?? 'unknown', 'error_message' => $data['error_message'] ?? null]);
            return null;
        }
        return $this->parseAddressComponents($data['result'] ?? []);
    }

    private function locationIqAutocomplete(string $query, array $options): array
    {
        $params = [
            'key' => $this->locationIqKey,
            'q' => $query,
            'limit' => 8,
            'dedupe' => 1,
            'countrycodes' => 'us',
            'format' => 'json',
        ];

        $url = $this->locationIqBaseUrl . '/autocomplete';
        $response = Http::withOptions(['verify' => false])->get($url, $params);

        // Fallback if /autocomplete endpoint is not found
        if ($response->status() === 404) {
            $url = $this->locationIqBaseUrl . '/autocomplete.php';
            $response = Http::withOptions(['verify' => false])->get($url, $params);
        }

        if (!$response->successful()) {
            Log::error('LocationIQ autocomplete error', [
                'url' => $url,
                'params' => $params,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
            throw new \Exception('LocationIQ autocomplete failed: ' . $response->status());
        }

        $items = $response->json();

        if (!is_array($items) || empty($items)) {
            Log::warning('LocationIQ returned empty result', ['url' => $url, 'response' => $response->body()]);
            return [];
        }

        // ✅ Include both parsed and raw data in each item
        return array_map(function ($it) {
            $addr = $it['address'] ?? [];
            $display = $it['display_name'] ?? '';
            $main = trim(($addr['house_number'] ?? '') . ' ' . ($addr['road'] ?? ''));
            $city = $addr['city'] ?? ($addr['town'] ?? ($addr['village'] ?? ''));
            $state = $addr['state'] ?? '';
            $zip = $addr['postcode'] ?? '';
            $secondary = trim(join(', ', array_filter([$city, $state, $zip])));

            return [
                'place_id' => (string)($it['place_id'] ?? ''),
                'description' => $display,
                'main_text' => $main ?: ($addr['neighbourhood'] ?? $display),
                'secondary_text' => $secondary,
                'types' => [$it['class'] ?? 'address'],
                'latitude' => isset($it['lat']) ? (float)$it['lat'] : null,
                'longitude' => isset($it['lon']) ? (float)$it['lon'] : null,
                'importance' => $it['importance'] ?? null,
                'osm_id' => $it['osm_id'] ?? null,
                'osm_type' => $it['osm_type'] ?? null,
                'raw' => $it, // 🚀 full raw data returned here
            ];
        }, $items);
    }



    private function locationIqDetails(string $placeId): ?array
    {
        $params = [
            'key' => $this->locationIqKey,
            'place_id' => $placeId,
            'format' => 'json',
        ];

        $urls = [
            $this->locationIqBaseUrl . '/lookup',
            $this->locationIqBaseUrl . '/lookup.php',
        ];

        $response = null;
        foreach ($urls as $url) {
            $response = Http::withOptions(['verify' => false])->get($url, $params);
            if ($response->successful()) break;
        }

        if (!$response || !$response->successful()) {
            Log::error('LocationIQ details lookup failed', [
                'place_id' => $placeId,
                'urls' => $urls,
                'status' => $response ? $response->status() : 'no response',
                'body' => $response ? $response->body() : null,
            ]);
            throw new \Exception('LocationIQ details lookup failed');
        }

        $data = $response->json();
        if (isset($data[0])) $data = $data[0];
        if (!is_array($data)) return null;

        return $this->parseLocationIqAddress($data);
    }


    private function parseLocationIqAddress(array $data): array
    {
        $addr = $data['address'] ?? [];
        $streetNumber = $addr['house_number'] ?? '';
        $streetName = $addr['road'] ?? ($addr['pedestrian'] ?? ($addr['path'] ?? ''));
        $address = trim(trim($streetNumber . ' ' . $streetName));

        return [
            'formatted_address' => $data['display_name'] ?? $address,
            'address' => $address,
            'city' => $addr['city'] ?? ($addr['town'] ?? ($addr['village'] ?? ($addr['hamlet'] ?? ''))),
            'state' => $addr['state'] ?? '',
            'zip' => $addr['postcode'] ?? '',
            'country' => $addr['country_code'] ?? '',
            'latitude' => isset($data['lat']) ? (float)$data['lat'] : null,
            'longitude' => isset($data['lon']) ? (float)$data['lon'] : null,
        ];
    }

    private function approxDistanceByCoordinates(array $origin, array $destination): ?array
    {
        // If coordinates exist in arrays, use haversine; otherwise, return null
        $olat = $origin['latitude'] ?? null;
        $olon = $origin['longitude'] ?? null;
        $dlat = $destination['latitude'] ?? null;
        $dlon = $destination['longitude'] ?? null;
        if ($olat === null || $olon === null || $dlat === null || $dlon === null) {
            return null;
        }
        $earth = 6371000; // meters
        $toRad = function ($deg) { return $deg * M_PI / 180; };
        $dPhi = $toRad($dlat - $olat);
        $dLam = $toRad($dlon - $olon);
        $phi1 = $toRad($olat);
        $phi2 = $toRad($dlat);
        $a = sin($dPhi/2)**2 + cos($phi1)*cos($phi2)*sin($dLam/2)**2;
        $c = 2 * atan2(sqrt($a), sqrt(1-$a));
        $meters = $earth * $c;
        // Rough driving time assuming 35 mph average (~15.6 m/s)
        $seconds = $meters / 15.6;
        return [
            'distance' => round($meters/1609.34, 2) . ' mi',
            'distance_value' => (int)$meters,
            'duration' => gmdate('H\h i\m', (int)$seconds),
            'duration_value' => (int)$seconds,
        ];
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

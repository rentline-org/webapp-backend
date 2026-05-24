<?php

namespace App\Services\Mapbox;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class GeocodingService
{
    protected ?string $accessToken;
    protected string $forwardEndpoint;

    /**
     * __construct
     *
     * @return void
     */
    public function __construct()
    {
        $this->accessToken = config('mapbox.access_token');
        $this->forwardEndpoint = config('mapbox.endpoints.geocode_forward');
    }

    /** @throws ConnectionException */
    public function forward(array $data): array
    {
        $response = Http::get($this->forwardEndpoint, [
            'access_token' => $this->accessToken,
            'address_line1' => $data['address_line1'] ?? null,
            'place' => $data['city'] ?? null,
            'region' => $data['state'] ?? null,
            'postcode' => $data['postal_code'] ?? null,
            'country' => strtoupper($data['country'] ?? ''),
            'autocomplete' => false,
            'limit' => 1,
        ]);

        if (! $response->successful()) {
            throw new RuntimeException('Mapbox geocoding failed: ' . $response->body());
        }

        $result = $response->json();

        if (empty($result['features'])) {
            return [
                'longitude' => null,
                'latitude' => null,
                'properties' => null,
                'raw' => $result,
            ];
        }

        $feature = $result['features'][0];
        $properties = $feature['properties'];

        return [
            'latitude' => $feature['geometry']['coordinates'][1] ?? null,
            'longitude' => $feature['geometry']['coordinates'][0] ?? null,
            'full_address' => $properties['full_address'] ?? null,
            'address_number' => $properties['context']['address']['address_number'] ?? null,
            'address' => $properties['context']['address']['street_name'] ?? null,
            'address_line' => $properties['context']['address']['name'] ?? null,
            'postal_code' => $properties['context']['postcode']['name'] ?? null,
            'city' => $properties['context']['place']['name'] ?? null,
            'state' => $properties['context']['region']['name'] ?? null,
            'region_code' => $properties['context']['region']['region_code'] ?? null,
        ];
    }
}

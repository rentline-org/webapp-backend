<?php

namespace App\Jobs;

use App\Enums\DataProcessingJobStatus;
use App\Models\Property;
use App\Services\DataProcessingJob\DataProcessingJobService;
use App\Services\Mapbox\GeocodingService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class GeocodePropertyJob implements ShouldQueue
{
    use Queueable;

    /** Create a new job instance. */
    public function __construct(protected int $propertyId, protected string $jobId) {}

    /**
     * Execute the job.
     *
     * @throws \Throwable
     */
    public function handle(): void
    {
        $dataProcessingJobService = app(DataProcessingJobService::class);
        $geocodingService = app(GeocodingService::class);

        $trackingJob = $dataProcessingJobService->findByJobId($this->jobId);

        $dataProcessingJobService->updateJobStatus($trackingJob, DataProcessingJobStatus::PROCESSING);

        try {
            $property = Property::query()->findOrFail($this->propertyId);

            $geolocation = $geocodingService->forward([
                'address_line1' => $property->address,
                'city' => $property->city,
                'state' => $property->state,
                'country' => $property->country,
                'postal_code' => $property->postal_code,
            ]);

            $property->update([
                'longitude' => $geolocation['longitude'],
                'latitude' => $geolocation['latitude'],
                'full_address' => $geolocation['properties']['full_address'],
                'address_number' => $geolocation['properties']['address_number'],
                'address' => $geolocation['properties']['address'],
                'postal_code' => $geolocation['properties']['postal_code'],
                'city' => $geolocation['properties']['city'],
                'state' => $geolocation['properties']['state'],
                'region_code' => $geolocation['properties']['region_code'],
            ]);

            $dataProcessingJobService->updateJobResults($trackingJob, 1, 1, 0);
        } catch (\Throwable $exception) {
            $dataProcessingJobService->updateJobError($trackingJob, $exception->getMessage());

            throw $exception;
        }
    }
}

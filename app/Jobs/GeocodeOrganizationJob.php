<?php

namespace App\Jobs;

use App\Enums\DataProcessingJobStatus;
use App\Models\Organization;
use App\Services\DataProcessingJob\DataProcessingJobService;
use App\Services\Mapbox\GeocodingService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class GeocodeOrganizationJob implements ShouldQueue
{
    use Queueable;

    public function __construct(protected int $organizationId, protected string $jobId) {}

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
            $organization = Organization::query()->findOrFail($this->organizationId);

            $geolocation = $geocodingService->forward([
                'address_line1' => $organization->address_line,
                'city' => $organization->city,
                'state' => $organization->state,
                'country' => $organization->country,
                'postal_code' => $organization->postal_code,
            ]);

            $organization->update([
                'address_line' => $geolocation['address_line'] ?? null,
                'city' => $geolocation['city'] ?? null,
                'state' => $geolocation['state'] ?? null,
                'postal_code' => $geolocation['postal_code'] ?? null,
                'latitude' => $geolocation['latitude'] ?? null,
                'longitude' => $geolocation['longitude'] ?? null,
                'full_address' => $geolocation['full_address'],
                'address_number' => $geolocation['address_number'],
                'region_code' => $geolocation['region_code'],
            ]);

            $dataProcessingJobService->updateJobResults($trackingJob, 1, 1, 0);
        } catch (\Throwable $exception) {
            logger()->error('GeocodeOrganizationJob failed', [
                'message' => $exception->getMessage(),
                'trace' => $exception->getTraceAsString(),
                'organization_id' => $this->organizationId,
                'job_id' => $this->jobId,
            ]);

            $dataProcessingJobService->updateJobError(
                $trackingJob,
                $exception->getMessage() ?: 'Unknown queue error'
            );

            throw $exception;
        }
    }
}

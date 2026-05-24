<?php

namespace App\Listeners;

use App\DTOs\DataProcessingJob\DataProcessingJobDTO;
use App\Enums\DataProcessingJobStatus;
use App\Enums\DataProcessingJobType;
use App\Events\OrganizationCreated;
use App\Jobs\GeocodeOrganizationJob;
use App\Jobs\GeocodePropertyJob;
use App\Services\DataProcessingJob\DataProcessingJobService;

class DispatchOrganizationGeocoding
{
    /** Create the event listener. */
    public function __construct(protected DataProcessingJobService $dataProcessingJobService) {}

    /** Handle the event. */
    public function handle(OrganizationCreated $event): void
    {
        $trackingJob = $this->dataProcessingJobService->createJob(DataProcessingJobDTO::fromArray([
            'type' => DataProcessingJobType::GEOCODING,
            'status' => DataProcessingJobStatus::PENDING,
            'user_id' => $event->user->id,
            'entity_type' => 'organization',
        ]));

        GeocodeOrganizationJob::dispatch($event->organization->id, $trackingJob->job_id);
    }
}

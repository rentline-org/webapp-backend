<?php

namespace App\Listeners;

use App\DTOs\DataProcessingJob\DataProcessingJobDTO;
use App\Enums\DataProcessingJobStatus;
use App\Enums\DataProcessingJobType;
use App\Events\PropertyCreated;
use App\Jobs\GeocodePropertyJob;
use App\Services\DataProcessingJob\DataProcessingJobService;

class DispatchPropertyGeocoding
{
    /** Create the event listener. */
    public function __construct(protected DataProcessingJobService $dataProcessingJobService) {}

    /** Handle the event. */
    public function handle(PropertyCreated $event): void
    {

        $trackingJob = $this->dataProcessingJobService->createJob(DataProcessingJobDTO::fromArray([
            'type' => DataProcessingJobType::GEOCODING,
            'status' => DataProcessingJobStatus::PENDING,
            'user_id' => $event->user->id,
            'entity_type' => 'property',
        ]));

        GeocodePropertyJob::dispatch($event->property->id, $trackingJob->job_id);
    }
}

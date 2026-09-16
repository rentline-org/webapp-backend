<?php

namespace App\Http\Controllers\Api\V1\Lease;

use App\Http\Controllers\Controller;
use App\Http\Requests\Lease\StoreLeaseAmendmentRequest;
use App\Http\Resources\Lease\LeaseAmendmentResource;
use App\Http\Resources\Lease\OperationalLeaseResource;
use App\Models\Lease;
use App\Models\LeaseAmendment;
use App\Services\Lease\LeaseService;
use Illuminate\Support\Facades\Gate;

class LeaseAmendmentController extends Controller
{
    public function __construct(private readonly LeaseService $leaseService) {}

    public function store(StoreLeaseAmendmentRequest $request, Lease $lease): LeaseAmendmentResource
    {
        Gate::authorize('update', $lease);

        return LeaseAmendmentResource::make(
            $this->leaseService->createAmendment($lease, $request->validated(), $request->user())
        );
    }

    public function activate(Lease $lease, LeaseAmendment $amendment): OperationalLeaseResource
    {
        Gate::authorize('update', $lease);

        return OperationalLeaseResource::make(
            $this->leaseService->activateAmendment($lease, $amendment, request()->user())
        );
    }
}

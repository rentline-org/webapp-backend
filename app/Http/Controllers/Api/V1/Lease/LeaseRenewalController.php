<?php

namespace App\Http\Controllers\Api\V1\Lease;

use App\Http\Controllers\Controller;
use App\Http\Requests\Lease\RenewLeaseRequest;
use App\Http\Resources\Lease\OperationalLeaseResource;
use App\Models\Lease;
use App\Services\Lease\LeaseService;
use Illuminate\Support\Facades\Gate;

class LeaseRenewalController extends Controller
{
    public function __construct(private readonly LeaseService $leaseService) {}

    public function store(RenewLeaseRequest $request, Lease $lease): OperationalLeaseResource
    {
        Gate::authorize('update', $lease);

        return OperationalLeaseResource::make(
            $this->leaseService->renew($lease, $request->validated())
        );
    }
}

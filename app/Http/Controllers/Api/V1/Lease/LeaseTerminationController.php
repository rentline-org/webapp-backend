<?php

namespace App\Http\Controllers\Api\V1\Lease;

use App\Http\Controllers\Controller;
use App\Http\Requests\Lease\TerminateLeaseRequest;
use App\Http\Resources\Lease\OperationalLeaseResource;
use App\Models\Lease;
use App\Services\Lease\LeaseService;
use Illuminate\Support\Facades\Gate;

class LeaseTerminationController extends Controller
{
    public function __construct(private readonly LeaseService $leaseService) {}

    public function store(TerminateLeaseRequest $request, Lease $lease): OperationalLeaseResource
    {
        Gate::authorize('update', $lease);

        return OperationalLeaseResource::make($this->leaseService->terminate(
            $lease,
            $request->user(),
            $request->validated('terminated_on'),
            $request->validated('reason'),
        ));
    }
}

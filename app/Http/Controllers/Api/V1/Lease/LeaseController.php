<?php

namespace App\Http\Controllers\Api\V1\Lease;

use App\Enums\LeaseWorkflowStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Lease\StoreLeaseRequest;
use App\Http\Requests\Lease\UpdateLeaseRequest;
use App\Http\Resources\Lease\OperationalLeaseResource;
use App\Models\Lease;
use App\Services\Lease\LeaseService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class LeaseController extends Controller
{
    public function __construct(private readonly LeaseService $leaseService) {}

    /** Display a listing of the resource. */
    public function index(Request $request): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', Lease::class);
        $filters = $request->validate([
            'search' => ['sometimes', 'string', 'max:255'],
            'workflow_status' => ['sometimes', Rule::enum(LeaseWorkflowStatus::class)],
            'temporal_status' => ['sometimes', Rule::in(['upcoming', 'current', 'active', 'expired'])],
            'property_id' => ['sometimes', 'integer', 'min:1'],
            'unit_id' => ['sometimes', 'integer', 'min:1'],
            'contact_id' => ['sometimes', 'integer', 'min:1'],
            'expiring_within_days' => ['sometimes', 'integer', 'between:0,3650'],
            'per_page' => ['sometimes', 'integer', 'between:1,100'],
        ]);

        return OperationalLeaseResource::collection(
            $this->leaseService->paginate($filters, $request->user())
        );
    }

    /** Store a newly created resource in storage. */
    public function store(StoreLeaseRequest $request): OperationalLeaseResource
    {
        Gate::authorize('create', Lease::class);

        return OperationalLeaseResource::make($this->leaseService->create($request->validated()));
    }

    /** Display the specified resource. */
    public function show(Lease $lease): OperationalLeaseResource
    {
        Gate::authorize('view', $lease);

        return OperationalLeaseResource::make($this->leaseService->load($lease));
    }

    /** Update the specified resource in storage. */
    public function update(UpdateLeaseRequest $request, Lease $lease): OperationalLeaseResource
    {
        Gate::authorize('update', $lease);

        return OperationalLeaseResource::make($this->leaseService->update($lease, $request->validated()));
    }

    /** Remove the specified resource from storage. */
    public function destroy(Lease $lease): Response
    {
        Gate::authorize('delete', $lease);
        $this->leaseService->delete($lease);

        return response()->noContent();
    }
}

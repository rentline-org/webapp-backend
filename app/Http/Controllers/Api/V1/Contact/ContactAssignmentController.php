<?php

namespace App\Http\Controllers\Api\V1\Contact;

use App\Http\Controllers\Controller;
use App\Http\Requests\Contact\StoreContactAssignmentRequest;
use App\Http\Requests\Contact\UpdateContactAssignmentRequest;
use App\Http\Resources\Contact\ContactAssignmentResource;
use App\Models\Contact;
use App\Models\ContactAssignment;
use App\Services\Contact\ContactAssignmentService;

class ContactAssignmentController extends Controller
{
    public function __construct(private readonly ContactAssignmentService $contactAssignmentService) {}

    /**
     * Display a listing of the resource.
     */
    public function index(Contact $contact)
    {
        $this->authorize('view', $contact);

        return ContactAssignmentResource::collection(
            $contact->assignments()->with(['property', 'unit'])->latest()->get()
        );
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreContactAssignmentRequest $request, Contact $contact)
    {
        return ContactAssignmentResource::make(
            $this->contactAssignmentService->create($contact, $request->validated())
        );
    }

    /**
     * Display the specified resource.
     */
    public function show(Contact $contact, ContactAssignment $assignment)
    {
        $this->authorize('view', $contact);
        abort_unless($assignment->contact_id === $contact->id, 404);

        return ContactAssignmentResource::make($assignment->load(['property', 'unit']));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateContactAssignmentRequest $request, Contact $contact, ContactAssignment $assignment)
    {
        return ContactAssignmentResource::make(
            $this->contactAssignmentService->update($contact, $assignment, $request->validated())
        );
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Contact $contact, ContactAssignment $assignment)
    {
        $this->authorize('update', $contact);
        $this->contactAssignmentService->delete($contact, $assignment);

        return response()->noContent();
    }
}

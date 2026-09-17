<?php

namespace App\Http\Controllers\Api\V1\Contact;

use App\DTOs\Contact\ContactDTO;
use App\Enums\ContactPersonType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Contact\ContactInsertUpdateRequest;
use App\Http\Resources\Contact\ContactResource;
use App\Models\Contact;
use App\Services\Contact\ContactService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/**
 * @group Contact Management
 * APIs for managing organization contacts and property assignments
 */
class ContactController extends Controller
{
    public function __construct(
        protected ContactService $contactService
    ) {}

    /** Display a listing of the resource. */
    public function index(Request $request)
    {
        Gate::authorize('viewAny', Contact::class);

        $contacts = $this->contactService->all($request->validate([
            'search' => ['sometimes', 'string', 'max:255'],
            'type' => ['sometimes', Rule::enum(ContactPersonType::class)],
            'property_id' => ['sometimes', 'integer', 'min:1'],
        ]), $request->user());

        return ContactResource::collection($contacts);
    }

    /** Store a newly created resource in storage. */
    public function store(ContactInsertUpdateRequest $request)
    {
        Gate::authorize('create', Contact::class);

        return ContactResource::make(
            $this->contactService->create(ContactDTO::fromRequest($request))
        );
    }

    /** Display the specified resource. */
    public function show(Contact $contact)
    {
        Gate::authorize('view', $contact);

        return ContactResource::make($contact->load([
            'properties',
            'assignments.property',
            'assignments.unit',
            'leaseParties.lease',
            'latestInvitation',
        ]));
    }

    /** Update the specified resource in storage. */
    public function update(ContactInsertUpdateRequest $request, Contact $contact)
    {
        Gate::authorize('update', $contact);

        return ContactResource::make(
            $this->contactService->update(
                $contact,
                ContactDTO::fromRequest($request, $contact)
            )
        );
    }

    /** Remove the specified resource from storage. */
    public function destroy(Contact $contact)
    {
        Gate::authorize('delete', $contact);

        $this->contactService->delete($contact);

        return response()->noContent();
    }
}

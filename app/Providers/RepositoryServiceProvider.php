<?php

namespace App\Providers;

use App\Repositories\Contact\ContactRepository;
use App\Repositories\Contracts\ContactRepositoryInterface;
use App\Repositories\Contracts\CustomListingRepositoryInterface;
use App\Repositories\Contracts\DataProcessingJobRepositoryInterface;
use App\Repositories\Contracts\DocumentRepositoryInterface;
use App\Repositories\Contracts\OrganizationRepositoryInterface;
use App\Repositories\Contracts\PropertyRepositoryInterface;
use App\Repositories\Contracts\RoleRepositoryInterface;
use App\Repositories\Contracts\UnitRepositoryInterface;
use App\Repositories\Contracts\UserRepositoryInterface;
use App\Repositories\CustomListing\CustomListingRepository;
use App\Repositories\DataProcessingJob\DataProcessingJobRepository;
use App\Repositories\Document\DocumentRepository;
use App\Repositories\Organization\OrganizationRepository;
use App\Repositories\Property\PropertyRepository;
use App\Repositories\Role\RoleRepository;
use App\Repositories\Unit\UnitRepository;
use App\Repositories\User\UserRepository;
use Illuminate\Support\ServiceProvider;

class RepositoryServiceProvider extends ServiceProvider
{
    /** Register services. */
    public function register(): void
    {
        $this->app->bind(DocumentRepositoryInterface::class, DocumentRepository::class);
        $this->app->bind(ContactRepositoryInterface::class, ContactRepository::class);
        $this->app->bind(UserRepositoryInterface::class, UserRepository::class);
        $this->app->bind(RoleRepositoryInterface::class, RoleRepository::class);
        $this->app->bind(DataProcessingJobRepositoryInterface::class, DataProcessingJobRepository::class);
        $this->app->bind(OrganizationRepositoryInterface::class, OrganizationRepository::class);
        $this->app->bind(PropertyRepositoryInterface::class, PropertyRepository::class);
        $this->app->bind(UnitRepositoryInterface::class, UnitRepository::class);
        $this->app->bind(CustomListingRepositoryInterface::class, CustomListingRepository::class);
    }

    /** Bootstrap services. */
    public function boot(): void
    {
        //
    }
}

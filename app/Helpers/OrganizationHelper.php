<?php

namespace App\Helpers;

use Illuminate\Support\Facades\Auth;

class OrganizationHelper
{
    public static function currentOrganizationId()
    {
        return Auth::user()?->currentAccessToken()?->organization_id;
    }
}

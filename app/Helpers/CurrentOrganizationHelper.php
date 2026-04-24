<?php

namespace App\Helpers;

use Illuminate\Support\Facades\Auth;

class CurrentOrganizationHelper
{
    public static function currentOrgId()
    {
        return Auth::user()?->currentAccessToken()?->organization_id;
    }
}

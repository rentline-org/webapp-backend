<?php

namespace App\Enums;

enum OrganizationPlan: string
{
    case TRIAL = 'trial';
    case STARTER = 'starter';
    case PREMIUM = 'pro';
    case ENTERPRISE = 'enterprise';
}

<?php

namespace App\Enums;

enum LeaseFinancialTermType: string
{
    case RENT = 'rent';
    case SECURITY_DEPOSIT = 'security_deposit';
    case MANAGEMENT_FEE = 'management_fee';
    case COMMISSION = 'commission';
    case INSURANCE_PREMIUM = 'insurance_premium';
    case SERVICE_COST = 'service_cost';
}

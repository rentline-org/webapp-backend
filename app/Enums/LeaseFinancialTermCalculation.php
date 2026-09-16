<?php

namespace App\Enums;

enum LeaseFinancialTermCalculation: string
{
    case FIXED = 'fixed';
    case PERCENTAGE = 'percentage';
}

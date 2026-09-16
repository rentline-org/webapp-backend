<?php

namespace App\Enums;

enum LeaseFinancialTermFrequency: string
{
    case ONE_TIME = 'one_time';
    case WEEKLY = 'weekly';
    case MONTHLY = 'monthly';
    case QUARTERLY = 'quarterly';
    case YEARLY = 'yearly';
}

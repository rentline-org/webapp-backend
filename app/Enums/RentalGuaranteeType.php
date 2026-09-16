<?php

namespace App\Enums;

enum RentalGuaranteeType: string
{
    case CASH_DEPOSIT = 'cash_deposit';
    case GUARANTOR = 'guarantor';
    case RENTAL_GUARANTEE_INSURANCE = 'rental_guarantee_insurance';
    case INVESTMENT_FUND_QUOTAS = 'investment_fund_quotas';
}

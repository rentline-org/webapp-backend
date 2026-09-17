<?php

namespace App\Enums;

enum ActionItemType: string
{
    case LEASE_EXPIRY = 'lease_expiry';
    case DOCUMENT_EXPIRY = 'document_expiry';
    case PENDING_SIGNATURE = 'pending_signature';
    case MISSING_MOVE_IN_INSPECTION = 'missing_move_in_inspection';
    case EXPIRED_INSURANCE = 'expired_insurance';
    case EXPIRED_COMPLIANCE = 'expired_compliance';
}

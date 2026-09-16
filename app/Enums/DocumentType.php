<?php

namespace App\Enums;

enum DocumentType: string
{
    case GENERIC = 'generic';
    case LEASE = 'lease';
    case LEASE_ADDENDUM = 'lease_addendum';
    case PROPERTY_MANAGEMENT_AGREEMENT = 'property_management_agreement';
    case BROKERAGE_AUTHORIZATION = 'brokerage_authorization';
    case INSPECTION_REPORT = 'inspection_report';
    case INSURANCE_POLICY = 'insurance_policy';
    case SERVICE_CONTRACT = 'service_contract';
    case COMPLIANCE_CERTIFICATE = 'compliance_certificate';
    case OWNERSHIP_RECORD = 'ownership_record';
    case CUSTOM = 'custom';
}

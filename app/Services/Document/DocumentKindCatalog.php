<?php

namespace App\Services\Document;

use App\Enums\DocumentType;
use App\Models\DocumentKind;
use App\Services\Organization\ActiveOrganizationContext;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Collection;

class DocumentKindCatalog
{
    /** @var list<string> */
    public const CATEGORIES = ['agreement', 'inspection', 'insurance', 'compliance', 'ownership', 'service', 'other'];

    /** @var list<string> */
    public const SCOPES = ['organization', 'property', 'unit', 'lease'];

    public function __construct(
        private readonly ActiveOrganizationContext $activeOrganizationContext,
    ) {}

    /** @return Collection<int, array<string, mixed>> */
    public function all(?string $locale = null): Collection
    {
        $locale ??= app()->getLocale();
        $systemKinds = collect(DocumentType::cases())
            ->reject(fn (DocumentType $type): bool => $type === DocumentType::CUSTOM)
            ->map(fn (DocumentType $type): array => $this->systemPayload($type, $locale));

        $customKinds = DocumentKind::query()
            ->where('organization_id', $this->activeOrganizationId())
            ->where('is_active', true)
            ->orderBy('label_en')
            ->get()
            ->map(fn (DocumentKind $kind): array => $this->customPayload($kind, $locale));

        return $systemKinds->concat($customKinds)->values();
    }

    /** @return array<string, mixed> */
    public function payloadFor(DocumentType $type, ?DocumentKind $customKind = null, ?string $locale = null): array
    {
        return $type === DocumentType::CUSTOM && $customKind !== null
            ? $this->customPayload($customKind, $locale ?? app()->getLocale())
            : $this->systemPayload($type, $locale ?? app()->getLocale());
    }

    /** @return array<string, mixed> */
    private function systemPayload(DocumentType $type, string $locale): array
    {
        $profile = $this->systemProfiles()[$type->value];

        return [
            'key' => $type->value,
            'type' => $type->value,
            'custom_kind_id' => null,
            'label' => str_starts_with($locale, 'pt') ? $profile['label_pt_br'] : $profile['label_en'],
            'label_en' => $profile['label_en'],
            'label_pt_br' => $profile['label_pt_br'],
            'category' => $profile['category'],
            'allowed_scopes' => $profile['allowed_scopes'],
            'supports_expiry' => $profile['supports_expiry'],
            'default_requires_signature' => $profile['default_requires_signature'],
            'required_parties' => $this->requiredParties($type),
            'capabilities' => $profile['capabilities'],
            'fields' => $profile['fields'],
            'is_system' => true,
        ];
    }

    /** @return array<string, mixed> */
    private function customPayload(DocumentKind $kind, string $locale): array
    {
        return [
            'key' => 'custom:' . $kind->key,
            'type' => DocumentType::CUSTOM->value,
            'custom_kind_id' => $kind->id,
            'label' => $kind->localizedLabel($locale),
            'label_en' => $kind->label_en,
            'label_pt_br' => $kind->label_pt_br,
            'category' => $kind->category,
            'allowed_scopes' => $kind->allowed_scopes,
            'supports_expiry' => $kind->supports_expiry,
            'default_requires_signature' => $kind->default_requires_signature,
            'required_parties' => [],
            'capabilities' => [
                'parties' => true,
                'signers' => true,
                'attachments' => true,
                'structured_details' => false,
            ],
            'fields' => [],
            'is_system' => false,
        ];
    }

    /** @return array<string, array<string, mixed>> */
    private function systemProfiles(): array
    {
        $commonCapabilities = [
            'parties' => true,
            'signers' => true,
            'attachments' => true,
            'structured_details' => true,
        ];

        return [
            'generic' => $this->profile('Generic document', 'Documento genérico', 'other', self::SCOPES, true, false, [], [
                ...$commonCapabilities,
                'structured_details' => false,
            ]),
            'lease' => $this->profile('Lease agreement', 'Contrato de locação', 'agreement', ['lease', 'property', 'unit'], true, true, [], $commonCapabilities),
            'lease_addendum' => $this->profile('Lease addendum', 'Aditivo de locação', 'agreement', ['lease'], true, true, [
                ['key' => 'change_summary', 'type' => 'text', 'required' => true],
            ], $commonCapabilities),
            'property_management_agreement' => $this->profile('Property management agreement', 'Contrato de administração imobiliária', 'agreement', ['property'], true, true, [
                ['key' => 'management_fee_type', 'type' => 'select', 'options' => ['fixed', 'percentage']],
                ['key' => 'management_fee_value', 'type' => 'decimal'],
            ], $commonCapabilities),
            'brokerage_authorization' => $this->profile('Brokerage/listing authorization', 'Autorização de corretagem/anúncio', 'agreement', ['property', 'unit'], true, true, [
                ['key' => 'exclusive', 'type' => 'boolean'],
                ['key' => 'commission_type', 'type' => 'select', 'options' => ['fixed', 'percentage']],
                ['key' => 'commission_value', 'type' => 'decimal'],
                ['key' => 'calculation_basis', 'type' => 'text'],
                ['key' => 'advertising_permitted', 'type' => 'boolean'],
                ['key' => 'creci_reference', 'type' => 'text'],
            ], $commonCapabilities),
            'inspection_report' => $this->profile('Inspection report', 'Laudo de vistoria', 'inspection', ['lease', 'property', 'unit'], false, false, [
                ['key' => 'inspection_type', 'type' => 'select', 'options' => ['move_in', 'move_out', 'routine'], 'required' => true],
                ['key' => 'inspected_on', 'type' => 'date', 'required' => true],
                ['key' => 'outcome', 'type' => 'text'],
            ], $commonCapabilities),
            'insurance_policy' => $this->profile('Insurance policy', 'Apólice de seguro', 'insurance', ['lease', 'property', 'unit'], true, false, [
                ['key' => 'provider', 'type' => 'text'],
                ['key' => 'policy_number', 'type' => 'text'],
                ['key' => 'coverage_amount', 'type' => 'decimal'],
                ['key' => 'premium_amount', 'type' => 'decimal'],
                ['key' => 'deductible_amount', 'type' => 'decimal'],
                ['key' => 'currency', 'type' => 'currency'],
            ], $commonCapabilities),
            'service_contract' => $this->profile('Service/vendor contract', 'Contrato de fornecedor/prestador', 'service', ['property', 'unit'], true, true, [
                ['key' => 'service_scope', 'type' => 'text'],
                ['key' => 'recurring_cost', 'type' => 'decimal'],
                ['key' => 'currency', 'type' => 'currency'],
                ['key' => 'frequency', 'type' => 'select', 'options' => ['one_time', 'monthly', 'quarterly', 'yearly']],
            ], $commonCapabilities),
            'compliance_certificate' => $this->profile('Compliance certificate', 'Certificado de conformidade', 'compliance', ['property', 'unit'], true, false, [
                ['key' => 'issuer', 'type' => 'text'],
                ['key' => 'certificate_number', 'type' => 'text'],
            ], $commonCapabilities),
            'ownership_record' => $this->profile('Ownership/title record', 'Matrícula/título de propriedade', 'ownership', ['property'], false, false, [
                ['key' => 'registry_office', 'type' => 'text'],
                ['key' => 'registration_number', 'type' => 'text'],
                ['key' => 'acquisition_date', 'type' => 'date'],
            ], $commonCapabilities),
        ];
    }

    /** @return array<string, mixed> */
    private function profile(
        string $labelEn,
        string $labelPtBr,
        string $category,
        array $allowedScopes,
        bool $supportsExpiry,
        bool $defaultRequiresSignature,
        array $fields,
        array $capabilities,
    ): array {
        return [
            'label_en' => $labelEn,
            'label_pt_br' => $labelPtBr,
            'category' => $category,
            'allowed_scopes' => $allowedScopes,
            'supports_expiry' => $supportsExpiry,
            'default_requires_signature' => $defaultRequiresSignature,
            'fields' => $fields,
            'capabilities' => $capabilities,
        ];
    }

    /** @return list<string> */
    private function requiredParties(DocumentType $type): array
    {
        return match ($type) {
            DocumentType::LEASE => ['tenant', 'landlord'],
            DocumentType::LEASE_ADDENDUM => ['tenant'],
            DocumentType::PROPERTY_MANAGEMENT_AGREEMENT => ['owner', 'manager'],
            DocumentType::BROKERAGE_AUTHORIZATION => ['owner', 'broker'],
            DocumentType::INSPECTION_REPORT => ['inspector'],
            DocumentType::INSURANCE_POLICY => ['insurer'],
            DocumentType::SERVICE_CONTRACT => ['vendor'],
            DocumentType::OWNERSHIP_RECORD => ['owner'],
            DocumentType::GENERIC,
            DocumentType::COMPLIANCE_CERTIFICATE,
            DocumentType::CUSTOM => [],
        };
    }

    private function activeOrganizationId(): int
    {
        return $this->activeOrganizationContext->id()
            ?? throw new AuthorizationException('An active organization is required.');
    }
}

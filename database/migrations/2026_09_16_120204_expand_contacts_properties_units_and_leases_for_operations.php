<?php

use App\Enums\ContactIdentityKind;
use App\Enums\LeaseWorkflowStatus;
use App\Enums\PropertyOperationalStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('contacts', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->after('organization_id')->constrained()->nullOnDelete();
            $table->string('identity_kind')->default(ContactIdentityKind::PERSON->value)->after('user_id');
            $table->string('preferred_locale', 10)->default('en')->after('phone');
            $table->string('tax_id_type', 10)->nullable()->after('preferred_locale');
            $table->text('tax_id_encrypted')->nullable()->after('tax_id_type');
            $table->string('tax_id_hash', 64)->nullable()->after('tax_id_encrypted');
            $table->string('tax_id_last4', 4)->nullable()->after('tax_id_hash');

            $table->unique(['organization_id', 'user_id']);
            $table->index(['organization_id', 'identity_kind']);
            $table->index(['organization_id', 'tax_id_hash']);
        });

        Schema::table('properties', function (Blueprint $table) {
            $table->string('operational_status')->default(PropertyOperationalStatus::ACTIVE->value);
            $table->timestamp('archived_at')->nullable();
            $table->index(['organization_id', 'operational_status', 'archived_at'], 'properties_operations_index');
        });

        Schema::table('units', function (Blueprint $table) {
            $table->string('operational_status')->default(PropertyOperationalStatus::ACTIVE->value);
            $table->timestamp('archived_at')->nullable();
            $table->index(['property_id', 'operational_status', 'archived_at'], 'units_operations_index');
        });

        Schema::table('leases', function (Blueprint $table) {
            $table->dropUnique(['document_id']);
            $table->dropForeign(['document_id']);
        });

        Schema::table('leases', function (Blueprint $table) {
            $table->foreignId('document_id')->nullable()->change();
            $table->foreign('document_id')->references('id')->on('documents')->nullOnDelete();
            $table->foreignId('organization_id')->nullable()->after('id')->constrained()->restrictOnDelete();
            $table->foreignId('property_id')->nullable()->after('organization_id')->constrained()->restrictOnDelete();
            $table->foreignId('unit_id')->nullable()->after('property_id')->constrained()->restrictOnDelete();
            $table->foreignId('renewed_from_id')->nullable()->after('document_id')->constrained('leases')->nullOnDelete();
            $table->foreignId('activated_by')->nullable()->after('renewed_from_id')->constrained('users')->nullOnDelete();
            $table->string('workflow_status')->default(LeaseWorkflowStatus::DRAFT->value)->after('activated_by');
            $table->string('guarantee_type')->nullable()->after('workflow_status');
            $table->timestamp('activated_at')->nullable()->after('guarantee_type');
            $table->date('terminated_on')->nullable()->after('activated_at');
            $table->text('termination_reason')->nullable()->after('terminated_on');
            $table->string('title')->nullable()->after('termination_reason');

            $table->string('property_title_snapshot')->nullable()->change();
            $table->string('unit_name_snapshot')->nullable()->change();
            $table->string('tenant_name_snapshot')->nullable()->change();
            $table->date('starts_on')->nullable()->change();
            $table->date('ends_on')->nullable()->change();
            $table->decimal('rent_amount', 12, 2)->nullable()->change();
            $table->string('currency', 3)->nullable()->change();

            $table->index(['organization_id', 'workflow_status', 'starts_on'], 'leases_org_workflow_start_index');
            $table->index(['unit_id', 'workflow_status', 'starts_on', 'ends_on'], 'leases_unit_period_index');
            $table->index(['organization_id', 'ends_on'], 'leases_org_end_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('leases', function (Blueprint $table) {
            $table->dropIndex('leases_org_workflow_start_index');
            $table->dropIndex('leases_unit_period_index');
            $table->dropIndex('leases_org_end_index');
            $table->dropConstrainedForeignId('organization_id');
            $table->dropConstrainedForeignId('property_id');
            $table->dropConstrainedForeignId('unit_id');
            $table->dropConstrainedForeignId('renewed_from_id');
            $table->dropConstrainedForeignId('activated_by');
            $table->dropColumn(['workflow_status', 'guarantee_type', 'activated_at', 'terminated_on', 'termination_reason', 'title']);
        });

        Schema::table('units', function (Blueprint $table) {
            $table->dropIndex('units_operations_index');
            $table->dropColumn(['operational_status', 'archived_at']);
        });

        Schema::table('properties', function (Blueprint $table) {
            $table->dropIndex('properties_operations_index');
            $table->dropColumn(['operational_status', 'archived_at']);
        });

        Schema::table('contacts', function (Blueprint $table) {
            $table->dropUnique(['organization_id', 'user_id']);
            $table->dropIndex(['organization_id', 'identity_kind']);
            $table->dropIndex(['organization_id', 'tax_id_hash']);
            $table->dropConstrainedForeignId('user_id');
            $table->dropColumn(['identity_kind', 'preferred_locale', 'tax_id_type', 'tax_id_encrypted', 'tax_id_hash', 'tax_id_last4']);
        });
    }
};

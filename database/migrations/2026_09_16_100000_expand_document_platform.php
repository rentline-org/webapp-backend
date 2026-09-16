<?php

use App\Enums\DocumentLifecycle;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('document_kinds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('key', 80);
            $table->string('label_en');
            $table->string('label_pt_br');
            $table->string('category', 50)->default('other');
            $table->json('allowed_scopes');
            $table->boolean('supports_expiry')->default(false);
            $table->boolean('default_requires_signature')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['organization_id', 'key']);
            $table->index(['organization_id', 'is_active']);
        });

        Schema::table('documents', function (Blueprint $table) {
            $table->foreignId('document_kind_id')
                ->nullable()
                ->after('type')
                ->constrained('document_kinds')
                ->nullOnDelete();
            $table->string('lifecycle', 30)
                ->default(DocumentLifecycle::DRAFT->value)
                ->after('document_kind_id');
            $table->string('reference_number')->nullable()->after('description');
            $table->date('issued_on')->nullable()->after('reference_number');
            $table->date('effective_on')->nullable()->after('issued_on');
            $table->date('expires_on')->nullable()->after('effective_on');
            $table->json('metadata')->nullable()->after('expires_on');
            $table->foreignId('supersedes_document_id')
                ->nullable()
                ->after('metadata')
                ->constrained('documents')
                ->nullOnDelete();
            $table->timestamp('archived_at')->nullable()->after('signed_at');
            $table->index(['organization_id', 'lifecycle', 'updated_at']);
            $table->index(['organization_id', 'expires_on']);
            $table->index(['organization_id', 'document_kind_id']);
        });

        Schema::create('document_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('version_number');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedBigInteger('primary_media_id')->nullable();
            $table->unsignedBigInteger('signed_media_id')->nullable();
            $table->string('notes', 1000)->nullable();
            $table->timestamp('finalized_at')->nullable();
            $table->timestamps();

            $table->unique(['document_id', 'version_number']);
        });

        Schema::create('document_property', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_id')->constrained()->cascadeOnDelete();
            $table->foreignId('property_id')->constrained()->restrictOnDelete();
            $table->string('relation_type', 50)->default('applies_to');
            $table->timestamps();
            $table->unique(['document_id', 'property_id', 'relation_type']);
            $table->index(['property_id', 'relation_type']);
        });

        Schema::create('document_unit', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_id')->constrained()->cascadeOnDelete();
            $table->foreignId('unit_id')->constrained()->restrictOnDelete();
            $table->string('relation_type', 50)->default('applies_to');
            $table->timestamps();
            $table->unique(['document_id', 'unit_id', 'relation_type']);
            $table->index(['unit_id', 'relation_type']);
        });

        Schema::create('document_lease', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_id')->constrained()->cascadeOnDelete();
            $table->foreignId('lease_id')->constrained()->restrictOnDelete();
            $table->string('relation_type', 50)->default('supporting');
            $table->timestamps();
            $table->unique(['document_id', 'lease_id', 'relation_type']);
            $table->index(['lease_id', 'relation_type']);
        });

        Schema::create('document_parties', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_id')->constrained()->cascadeOnDelete();
            $table->foreignId('contact_id')->nullable()->constrained()->nullOnDelete();
            $table->string('role', 50);
            $table->string('name_snapshot');
            $table->string('email_snapshot')->nullable();
            $table->boolean('is_primary')->default(false);
            $table->decimal('ownership_percentage', 5, 2)->nullable();
            $table->timestamps();

            $table->index(['document_id', 'role']);
            $table->index(['contact_id', 'role']);
        });

        Schema::create('document_signers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_id')->constrained()->cascadeOnDelete();
            $table->foreignId('document_version_id')->constrained()->cascadeOnDelete();
            $table->foreignId('contact_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name_snapshot');
            $table->string('email_snapshot')->nullable();
            $table->string('role', 50)->default('signer');
            $table->string('status', 30)->default('pending');
            $table->timestamp('signed_at')->nullable();
            $table->foreignId('acted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('provider')->nullable();
            $table->string('provider_envelope_id')->nullable();
            $table->string('provider_signer_id')->nullable();
            $table->timestamps();

            $table->index(['document_id', 'status']);
        });

        Schema::create('document_shares', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_id')->constrained()->cascadeOnDelete();
            $table->foreignId('contact_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('granted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('revoked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('granted_at');
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();

            $table->unique(['document_id', 'user_id']);
            $table->index(['user_id', 'revoked_at']);
        });

        Schema::create('document_audit_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('document_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('event', 80);
            $table->json('metadata')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['document_id', 'created_at']);
            $table->index(['organization_id', 'event', 'created_at']);
        });

        DB::table('documents')->orderBy('id')->eachById(function (object $document): void {
            DB::table('document_versions')->insert([
                'document_id' => $document->id,
                'version_number' => 1,
                'created_by' => $document->uploaded_by,
                'created_at' => $document->created_at,
                'updated_at' => $document->updated_at,
            ]);

            if ($document->property_id !== null) {
                DB::table('document_property')->insert([
                    'document_id' => $document->id,
                    'property_id' => $document->property_id,
                    'relation_type' => 'applies_to',
                    'created_at' => $document->created_at,
                    'updated_at' => $document->updated_at,
                ]);
            }

            if ($document->unit_id !== null) {
                DB::table('document_unit')->insert([
                    'document_id' => $document->id,
                    'unit_id' => $document->unit_id,
                    'relation_type' => 'applies_to',
                    'created_at' => $document->created_at,
                    'updated_at' => $document->updated_at,
                ]);
            }
        }, 'id');
    }

    public function down(): void
    {
        Schema::dropIfExists('document_audit_events');
        Schema::dropIfExists('document_shares');
        Schema::dropIfExists('document_signers');
        Schema::dropIfExists('document_parties');
        Schema::dropIfExists('document_lease');
        Schema::dropIfExists('document_unit');
        Schema::dropIfExists('document_property');
        Schema::dropIfExists('document_versions');

        Schema::table('documents', function (Blueprint $table) {
            $table->dropForeign(['document_kind_id']);
            $table->dropForeign(['supersedes_document_id']);
            $table->dropIndex(['organization_id', 'lifecycle', 'updated_at']);
            $table->dropIndex(['organization_id', 'expires_on']);
            $table->dropIndex(['organization_id', 'document_kind_id']);
            $table->dropColumn([
                'document_kind_id',
                'lifecycle',
                'reference_number',
                'issued_on',
                'effective_on',
                'expires_on',
                'metadata',
                'supersedes_document_id',
                'archived_at',
            ]);
        });

        Schema::dropIfExists('document_kinds');
    }
};

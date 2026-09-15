<?php

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
        Schema::create('documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('property_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('unit_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('signed_by')->nullable()->constrained('users')->nullOnDelete();

            // Kept as a string so new document kinds can be introduced without
            // changing a database enum. Type-specific fields live in extension tables.
            $table->string('type', 50);
            $table->string('title');
            $table->string('purpose', 500);
            $table->text('description')->nullable();
            $table->boolean('requires_signature')->default(false);
            $table->boolean('is_signed')->default(false);
            $table->timestamp('signed_at')->nullable();
            $table->timestamps();

            $table->index(['organization_id', 'type', 'updated_at']);
            $table->index(['organization_id', 'requires_signature', 'is_signed']);
            $table->index(['organization_id', 'property_id', 'updated_at']);
            $table->index(['organization_id', 'unit_id', 'updated_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('documents');
    }
};

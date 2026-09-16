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
        Schema::create('contact_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('contact_id')->constrained()->cascadeOnDelete();
            $table->foreignId('property_id')->constrained()->restrictOnDelete();
            $table->foreignId('unit_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('lease_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('role');
            $table->string('source')->default('manual');
            $table->boolean('is_primary')->default(false);
            $table->date('starts_on')->nullable();
            $table->date('ends_on')->nullable();
            $table->decimal('ownership_percentage', 7, 4)->nullable();
            $table->timestamps();

            $table->index(['organization_id', 'property_id', 'role'], 'contact_assignments_property_index');
            $table->index(['organization_id', 'unit_id', 'role'], 'contact_assignments_unit_index');
            $table->index(['lease_id', 'contact_id', 'role'], 'contact_assignments_lease_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('contact_assignments');
    }
};

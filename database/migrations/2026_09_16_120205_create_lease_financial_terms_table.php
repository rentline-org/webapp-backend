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
        Schema::create('lease_financial_terms', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('lease_id')->constrained()->cascadeOnDelete();
            $table->foreignId('lease_amendment_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('payer_contact_id')->nullable()->constrained('contacts')->nullOnDelete();
            $table->foreignId('payee_contact_id')->nullable()->constrained('contacts')->nullOnDelete();
            $table->string('type');
            $table->string('calculation')->default('fixed');
            $table->decimal('amount', 14, 2)->nullable();
            $table->decimal('percentage', 8, 4)->nullable();
            $table->string('currency', 3)->nullable();
            $table->string('frequency')->default('monthly');
            $table->string('calculation_basis')->nullable();
            $table->unsignedTinyInteger('due_day')->nullable();
            $table->date('effective_from')->nullable();
            $table->date('effective_to')->nullable();
            $table->boolean('is_liability')->default(false);
            $table->timestamps();

            $table->index(['lease_id', 'type', 'effective_from']);
            $table->index(['organization_id', 'type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('lease_financial_terms');
    }
};

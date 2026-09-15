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
        Schema::create('leases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('tenant_contact_id')->nullable()->constrained('contacts')->nullOnDelete();
            $table->string('property_title_snapshot');
            $table->string('unit_name_snapshot');
            $table->string('tenant_name_snapshot');
            $table->string('tenant_email_snapshot')->nullable();
            $table->date('starts_on');
            $table->date('ends_on');
            $table->decimal('rent_amount', 12, 2);
            $table->string('currency', 3);
            $table->decimal('security_deposit', 12, 2)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['starts_on', 'ends_on']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('leases');
    }
};

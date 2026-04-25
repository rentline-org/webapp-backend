<?php

use App\Enums\OrganizationPlan;
use App\Enums\TaxIDType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Run the migrations. */
    public function up(): void
    {
        Schema::create('organizations', function (Blueprint $table) {
            $table->id();

            $table->string('title');
            $table->string('description')->nullable();

            $table->string('phone')->nullable();
            $table->string('email')->unique()->index();
            $table->string('website')->nullable();

            $table->foreignId('owner_id')->constrained('users')->cascadeOnDelete();

            $table->string('country', 2);
            $table->string('state')->nullable();
            $table->string('city');
            $table->string('postal_code');
            $table->string('address_line');

            $table->string('currency', 3)->default('BRL');

            $table->string('timezone')->default('America/Sao_Paulo');

            $table->string('tax_id')->nullable();
            $table->enum('tax_id_type', TaxIDType::cases())->nullable();

            $table->enum('plan', OrganizationPlan::cases())->default(OrganizationPlan::TRIAL);
            $table->boolean('is_plan_active')->default(true);

            $table->timestamp('data_retention_until')->nullable();
            $table->boolean('is_active')->default(true);

            $table->json('settings')->nullable();

            $table->timestamp('trial_ends_at')->nullable();

            $table->index(['country', 'state', 'city']);
            $table->index('plan');
            $table->index('is_plan_active');

            $table->softDeletes();
            $table->timestamps();
        });
    }

    /** Reverse the migrations. */
    public function down(): void
    {
        Schema::dropIfExists('organizations');
    }
};

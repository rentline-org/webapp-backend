<?php

use App\Enums\PropertyType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('properties', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();

            $table->string('slug');
            $table->unique(['organization_id', 'slug']);

            $table->string('title');
            $table->text('description')->nullable();

            // Location
            $table->string('address');
            $table->string('city');
            $table->string('state');
            $table->string('postal_code');
            $table->string('country')->nullable();

            // Structure only (not business logic)
            $table->enum(
                'property_type',
                array_map(fn ($case) => $case->value, PropertyType::cases())
            )->default(PropertyType::SINGLE_UNIT->value);

            $table->timestamps();

            $table->index(['organization_id', 'property_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('properties');
    }
};

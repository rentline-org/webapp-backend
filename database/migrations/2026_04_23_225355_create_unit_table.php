<?php

use App\Enums\UnitType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('units', function (Blueprint $table) {
            $table->id();
            $table->foreignId('property_id')->constrained()->cascadeOnDelete();

            // Identity
            $table->string('name'); // "Unit 3B", "Main House", etc.
            $table->text('description')->nullable();

            $table->enum(
                'unit_type',
                array_map(fn ($case) => $case->value, UnitType::cases())
            );

            // Business logic lives HERE
            $table->boolean('is_available')->default(true);
            $table->boolean('is_furnished')->default(false);
            $table->boolean('is_pet_friendly')->default(false);

            // Pricing
            $table->decimal('rent_price', 10, 2)->nullable();
            $table->decimal('sale_price', 10, 2)->nullable();
            $table->decimal('buy_price', 10, 2)->nullable();

            // Details
            $table->integer('bedrooms')->nullable();
            $table->integer('bathrooms')->nullable();
            $table->decimal('square_feet', 10, 2)->nullable();

            // Extras
            $table->json('amenities')->nullable();
            $table->json('sale_types')->nullable();

            // Availability
            $table->date('available_from')->nullable();

            $table->timestamps();

            $table->index(['property_id', 'unit_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('units');
    }
};

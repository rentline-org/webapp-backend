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
            $table->string('description')->nullable();

            $table->string('address');
            $table->string('city');
            $table->string('state');
            $table->string('postal_code');
            $table->string('country')->nullable();

            $table->enum(
                'property_type',
                array_map(fn ($case) => $case->value, PropertyType::cases())
            )->default(PropertyType::HOUSE->value);

            $table->boolean('is_available')->default(true);
            $table->boolean('is_furnished')->default(false);
            $table->decimal('rent_price', 10, 2)->nullable();
            $table->decimal('sale_price', 10, 2)->nullable();

            $table->decimal('buy_price', 10, 2)->nullable();

            $table->integer('bedrooms')->nullable();
            $table->integer('bathrooms')->nullable();
            $table->decimal('square_feet', 10, 2)->nullable();

            $table->json('amenities')->nullable();
            $table->date('available_from')->nullable();
            $table->boolean('is_pet_friendly')->default(false);

            $table->json('sale_types')->nullable();

            $table->timestamps();

            $table->index(['organization_id', 'property_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('properties');
    }
};

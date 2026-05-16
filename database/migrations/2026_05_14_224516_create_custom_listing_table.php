<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Run the migrations. */
    public function up(): void
    {
        Schema::create('custom_listings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('listing_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('subdomain')->unique();

            $table->string('headline')->nullable();
            $table->boolean('is_published')->default(false);

            $table->boolean('use_organization_defaults')->default(true); // uses organization phone and email

            $table->boolean(column: 'show_contact_form')->default(false);
            $table->boolean('show_phone')->default(true);
            $table->boolean('show_email')->default(true);

            $table->string('contact_email')->nullable();
            $table->string('contact_phone')->nullable();

            $table->json('languages')->nullable();

            $table->index(['subdomain']);
            $table->timestamps();
        });
    }

    /** Reverse the migrations. */
    public function down(): void
    {
        Schema::dropIfExists('custom_listing');
    }
};

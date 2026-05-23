<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Run the migrations. */
    public function up(): void
    {
        Schema::table('properties', function (Blueprint $table) {
            $table->string('full_address')->nullable()->after('address');
            $table->string('address_number')->nullable()->after('full_address');
            $table->string('region_code')->nullable()->after('state');
        });
    }

    /** Reverse the migrations. */
    public function down(): void
    {
        Schema::table('properties', function (Blueprint $table) {
            $table->dropColumn('full_address');
            $table->dropColumn('address_number');
            $table->dropColumn('region_code');
        });
    }
};

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
        Schema::table('users', function (Blueprint $table) {
            $table->string('locale', 10)->default('en')->after('email');
        });

        Schema::table('organization_user', function (Blueprint $table) {
            $table->string('role', 24)->default('manager')->after('organization_id');
            $table->string('status', 24)->default('active')->after('role');
            $table->foreignId('invited_by')->nullable()->after('status')->constrained('users')->nullOnDelete();
            $table->timestamp('accepted_at')->nullable()->after('invited_by');
            $table->index(['organization_id', 'status', 'role'], 'organization_membership_lookup');
        });

        Schema::create('organization_invitations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('contact_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('invited_by')->constrained('users')->cascadeOnDelete();
            $table->string('email');
            $table->string('role', 24);
            $table->string('locale', 10)->default('en');
            $table->string('token_hash', 64)->unique();
            $table->timestamp('expires_at');
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();

            $table->index(['organization_id', 'email']);
            $table->index(['organization_id', 'role', 'expires_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('organization_invitations');

        Schema::table('organization_user', function (Blueprint $table) {
            $table->dropIndex('organization_membership_lookup');
            $table->dropConstrainedForeignId('invited_by');
            $table->dropColumn(['role', 'status', 'accepted_at']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('locale');
        });
    }
};

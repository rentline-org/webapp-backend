<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Run the migrations. */
    public function up(): void
    {
        Schema::create('contact_property_stage', function (Blueprint $table) {
            $table->unsignedBigInteger('contact_id');
            $table->unsignedBigInteger('property_id');
            $table->primary(['contact_id', 'property_id']);
        });

        $legacyContactCount = DB::table('contacts')
            ->whereNotNull('property_id')
            ->count();

        DB::table('contacts')
            ->select(['id', 'property_id'])
            ->whereNotNull('property_id')
            ->orderBy('id')
            ->chunkById(500, function ($contacts): void {
                DB::table('contact_property_stage')->insertOrIgnore(
                    $contacts->map(fn ($contact) => [
                        'contact_id' => $contact->id,
                        'property_id' => $contact->property_id,
                    ])->all()
                );
            });

        if (DB::table('contact_property_stage')->count() !== $legacyContactCount) {
            throw new RuntimeException('Not every legacy contact property assignment was migrated.');
        }

        Schema::table('contacts', function (Blueprint $table) {
            $table->dropConstrainedForeignId('property_id');
        });

        Schema::create('contact_property', function (Blueprint $table) {
            $table->foreignId('contact_id')->constrained()->cascadeOnDelete();
            $table->foreignId('property_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->primary(['contact_id', 'property_id']);
            $table->index(['property_id', 'contact_id']);
        });

        DB::table('contact_property_stage')
            ->orderBy('contact_id')
            ->chunkById(500, function ($assignments): void {
                $timestamp = now();

                DB::table('contact_property')->insert(
                    $assignments->map(fn ($assignment) => [
                        'contact_id' => $assignment->contact_id,
                        'property_id' => $assignment->property_id,
                        'created_at' => $timestamp,
                        'updated_at' => $timestamp,
                    ])->all()
                );
            }, 'contact_id');

        if (DB::table('contact_property')->count() !== $legacyContactCount) {
            throw new RuntimeException('Not every staged contact property assignment was migrated.');
        }

        Schema::drop('contact_property_stage');
    }

    /** Reverse the migrations. */
    public function down(): void
    {
        Schema::create('contact_property_stage', function (Blueprint $table) {
            $table->unsignedBigInteger('contact_id');
            $table->unsignedBigInteger('property_id');
            $table->primary(['contact_id', 'property_id']);
        });

        DB::table('contact_property')
            ->selectRaw('contact_id, MIN(property_id) AS property_id')
            ->groupBy('contact_id')
            ->orderBy('contact_id')
            ->chunkById(500, function ($assignments): void {
                DB::table('contact_property_stage')->insert(
                    $assignments->map(fn ($assignment) => [
                        'contact_id' => $assignment->contact_id,
                        'property_id' => $assignment->property_id,
                    ])->all()
                );
            }, 'contact_id');

        Schema::table('contacts', function (Blueprint $table) {
            $table->foreignId('property_id')
                ->nullable()
                ->constrained()
                ->cascadeOnDelete();
        });

        DB::table('contacts')
            ->select('id')
            ->orderBy('id')
            ->chunkById(500, function ($contacts): void {
                foreach ($contacts as $contact) {
                    $propertyId = DB::table('contact_property_stage')
                        ->where('contact_id', $contact->id)
                        ->orderBy('property_id')
                        ->value('property_id');

                    if ($propertyId !== null) {
                        DB::table('contacts')
                            ->where('id', $contact->id)
                            ->update(['property_id' => $propertyId]);
                    }
                }
            });

        Schema::dropIfExists('contact_property');
        Schema::drop('contact_property_stage');
    }
};

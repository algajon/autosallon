<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vehicles', function (Blueprint $table) {
            // Base
            $table->bigIncrements('id');

            // Canonical (Albanian) fields — used by scraper + app
            $table->string('prodhuesi', 100)->nullable();         // brand
            $table->string('modeli', 100)->nullable();            // model
            $table->string('varianti', 150)->nullable();          // trim/grade
            $table->unsignedSmallInteger('viti')->nullable()->index(); // year (YYYY only)

            $table->unsignedInteger('cmimi_eur')->nullable()->index();     // normalized, rounded (ends with 0)
            $table->unsignedInteger('kilometrazhi_km')->nullable()->index(); // numeric km

            $table->string('karburanti', 50)->nullable();         // mapped to Albanian (Benzinë, Dizel, ...)
            $table->string('ngjyra', 50)->nullable();
            $table->string('transmisioni', 50)->nullable();
            $table->unsignedTinyInteger('uleset')->nullable();    // 1..9
            $table->string('vin', 32)->nullable()->unique();
            $table->unsignedInteger('engine_cc')->nullable();

            // Media / URLs / Blobs
            $table->json('images')->nullable();                   // scraper writes JSON array (prefer JSON over delimited string)
            $table->string('listing_url', 1024)->nullable()->index();
            $table->longText('opsionet')->nullable();             // semicolon-delimited features (you may later split to a pivot)
            $table->text('raporti_url')->nullable();              // potentially multiple links, “;” separated

            // English mirror fields (optional; kept for search & form aliasing)
            $table->string('manufacturer', 100)->nullable();
            $table->string('model', 100)->nullable();
            $table->string('grade', 150)->nullable();
            $table->string('year', 50)->nullable();
            $table->string('price', 100)->nullable();
            $table->string('mileage', 50)->nullable();
            $table->string('fuel', 50)->nullable();
            $table->string('transmission', 50)->nullable();
            $table->string('color', 50)->nullable();
            $table->unsignedTinyInteger('seats')->nullable();

            // Housekeeping
            $table->timestamps();
            $table->softDeletes();

            // Common filter indexes
            $table->index(['viti', 'cmimi_eur']);
            $table->index(['viti', 'kilometrazhi_km']);
        });

        // MySQL 8+ FULLTEXT for your search (boolean mode)
        // Note: only works on InnoDB / MySQL 5.7+ (ideally 8+). Safe to wrap in try/catch if you support SQLite in tests.
        Schema::table('vehicles', function (Blueprint $table) {
            $table->fullText([
                'prodhuesi','modeli','varianti','vin','ngjyra','karburanti','transmisioni',
                'manufacturer','model','grade','color','fuel','transmission'
            ], 'vehicles_ft');
        });
    }

    public function down(): void
    {
        // Drop FT index first if your MySQL requires it explicitly
        try {
            DB::statement('ALTER TABLE vehicles DROP INDEX vehicles_ft');
        } catch (\Throwable $e) {
            // ignore if not present / different driver
        }

        Schema::dropIfExists('vehicles');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('vehicles', function (Blueprint $table) {
            $table->id();

            // Core scraped fields (SQ names only — match your CSV/scraper)
            $table->string('prodhuesi',   100)->nullable()->index();
            $table->string('modeli',      100)->nullable()->index();
            $table->string('varianti',    150)->nullable();

            // Year & numeric specs
            $table->string('viti', 10)->nullable()->index();                 // year as string (scraper provides plain year)
            $table->integer('cmimi_eur')->nullable()->index();               // already rounded by scraper
            $table->unsignedInteger('kilometrazhi_km')->nullable()->index(); // mileage in KM (digits only)

            // Spec labels
            $table->string('karburanti',    50)->nullable()->index();
            $table->string('ngjyra',        50)->nullable()->index();
            $table->string('transmisioni',  50)->nullable()->index();

            // Seats/VIN/engine
            $table->unsignedTinyInteger('uleset')->nullable()->index();
            $table->string('vin', 64)->nullable()->index();
            $table->unsignedInteger('engine_cc')->nullable();

            // Media & links
            // Store images as JSON array (but your controller also accepts ";"-joined text)
            $table->json('images')->nullable();
            $table->string('listing_url', 2048)->nullable();
            $table->text('opsionet')->nullable();     // semicolon list from scraper
            $table->text('raporti_url')->nullable();  // semicolon list; first is primary

            $table->timestamps();

            // MySQL FULLTEXT for search (requires InnoDB + MySQL 5.7+ / MariaDB 10.3+)
            $table->fullText([
                'prodhuesi','modeli','varianti','vin','ngjyra','karburanti','transmisioni'
            ], 'vehicles_fulltext');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vehicles');
    }
};

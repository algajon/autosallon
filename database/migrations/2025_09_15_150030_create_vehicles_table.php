<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('vehicles', function (Blueprint $table) {
            $table->id();
            $table->string('prodhuesi', 100);
            $table->string('modeli', 150);
            $table->string('varianti', 200)->nullable();
            $table->unsignedSmallInteger('viti')->nullable();
            $table->string('regjistrimi', 7)->nullable(); // MM/YYYY
            $table->unsignedInteger('cmimi_eur')->nullable(); // store whole EUR
            $table->unsignedInteger('kilometrazhi_km')->nullable();
            $table->string('karburanti', 30)->nullable();
            $table->string('ngjyra', 30)->nullable();
            $table->string('transmisioni', 50)->nullable();
            $table->unsignedTinyInteger('uleset')->nullable();
            $table->string('vin', 25)->nullable()->index();
            $table->json('images')->nullable();
            $table->timestamps();

            $table->index(['prodhuesi', 'modeli']);
            $table->index(['viti']);
            $table->index(['cmimi_eur']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vehicles');
    }
};

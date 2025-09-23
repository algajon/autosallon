<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('vehicles', function (Blueprint $table) {
            // Create a combined FULLTEXT index across the search fields
            $table->fullText(
                ['prodhuesi','modeli','varianti','vin','ngjyra','karburanti','transmisioni'],
                'vehicles_ftxt'
            );
        });
    }

    public function down(): void
    {
        Schema::table('vehicles', function (Blueprint $table) {
            $table->dropFullText('vehicles_ftxt');
        });
    }
};

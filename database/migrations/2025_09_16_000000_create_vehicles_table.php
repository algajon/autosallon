<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vehicles', function (Blueprint $table) {
            // Name indexes explicitly so we can drop them reliably
            $table->index('prodhuesi',       'vehicles_prodhuesi_idx');
            $table->index('modeli',          'vehicles_modeli_idx');
            $table->index('varianti',        'vehicles_varianti_idx');
            $table->index('viti',            'vehicles_viti_idx');
            $table->index('cmimi_eur',       'vehicles_cmimi_eur_idx');
            $table->index('kilometrazhi_km', 'vehicles_kilometrazhi_km_idx');
            $table->index('karburanti',      'vehicles_karburanti_idx');
            $table->index('transmisioni',    'vehicles_transmisioni_idx');
            $table->index('ngjyra',          'vehicles_ngjyra_idx');
            $table->index('uleset',          'vehicles_uleset_idx');
            $table->index('created_at',      'vehicles_created_at_idx');

            // Optional: VIN unique (beware of duplicates in existing data)
            // $table->unique('vin', 'vehicles_vin_unique');
        });
    }

    public function down(): void
    {
        Schema::table('vehicles', function (Blueprint $table) {
            $table->dropIndex('vehicles_prodhuesi_idx');
            $table->dropIndex('vehicles_modeli_idx');
            $table->dropIndex('vehicles_varianti_idx');
            $table->dropIndex('vehicles_viti_idx');
            $table->dropIndex('vehicles_cmimi_eur_idx');
            $table->dropIndex('vehicles_kilometrazhi_km_idx');
            $table->dropIndex('vehicles_karburanti_idx');
            $table->dropIndex('vehicles_transmisioni_idx');
            $table->dropIndex('vehicles_ngjyra_idx');
            $table->dropIndex('vehicles_uleset_idx');
            $table->dropIndex('vehicles_created_at_idx');

            // $table->dropUnique('vehicles_vin_unique');
        });
    }
};

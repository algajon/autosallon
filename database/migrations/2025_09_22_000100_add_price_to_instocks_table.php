<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('instocks', function (Blueprint $table) {
            $table->unsignedInteger('price')->nullable()->after('description');
        });
    }

    public function down(): void
    {
        Schema::table('instocks', function (Blueprint $table) {
            $table->dropColumn('price');
        });
    }
};

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
Schema::table('vehicles', function (Blueprint $t) {
    $t->string('source', 32)->default('encar')->index();   // marketplace name
    $t->string('external_id', 64)->nullable()->index();    // e.g. carid
    $t->unique(['source','external_id']);                  // idempotency key
});
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('vehicles', function (Blueprint $table) {
            //
        });
    }
};

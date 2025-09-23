<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Drop the boolean flag if it exists
            if (Schema::hasColumn('users', 'is_admin')) {
                $table->dropColumn('is_admin');
            }

            // Make sure role exists and defaults to guest
            if (! Schema::hasColumn('users', 'role')) {
                $table->string('role', 16)->default('guest')->index();
            } else {
                // tighten type/default/index if needed
                $table->string('role', 16)->default('guest')->index()->change();
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Recreate the column on rollback (optional)
            if (! Schema::hasColumn('users', 'is_admin')) {
                $table->boolean('is_admin')->default(false)->after('password');
            }
            // You can’t easily “un-change” the role default here; it’s fine.
        });
    }
};
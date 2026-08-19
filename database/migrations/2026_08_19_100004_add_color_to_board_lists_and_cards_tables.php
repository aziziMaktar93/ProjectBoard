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
        Schema::table('board_lists', function (Blueprint $table) {
            $table->string('color')->nullable()->after('position');
        });

        Schema::table('cards', function (Blueprint $table) {
            $table->string('color')->nullable()->after('description');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('board_lists', function (Blueprint $table) {
            $table->dropColumn('color');
        });

        Schema::table('cards', function (Blueprint $table) {
            $table->dropColumn('color');
        });
    }
};

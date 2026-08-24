<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('board_events', function (Blueprint $table) {
            $table->dropForeign(['board_id']);
        });

        Schema::table('board_events', function (Blueprint $table) {
            $table->foreignId('board_id')->nullable()->change();
        });

        Schema::table('board_events', function (Blueprint $table) {
            $table->foreign('board_id')->references('id')->on('boards')->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('board_events')->whereNull('board_id')->delete();

        Schema::table('board_events', function (Blueprint $table) {
            $table->dropForeign(['board_id']);
        });

        Schema::table('board_events', function (Blueprint $table) {
            $table->foreignId('board_id')->nullable(false)->change();
        });

        Schema::table('board_events', function (Blueprint $table) {
            $table->foreign('board_id')->references('id')->on('boards')->cascadeOnDelete();
        });
    }
};

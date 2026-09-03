<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('board_user', function (Blueprint $table) {
            $table->timestamp('chat_last_read_at')->nullable()->after('is_favourite');
        });
    }

    public function down(): void
    {
        Schema::table('board_user', function (Blueprint $table) {
            $table->dropColumn('chat_last_read_at');
        });
    }
};

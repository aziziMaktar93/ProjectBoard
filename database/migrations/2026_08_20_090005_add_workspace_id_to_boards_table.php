<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('boards', function (Blueprint $table) {
            $table->foreignId('workspace_id')->nullable()->after('user_id')->constrained()->cascadeOnDelete();
        });

        $users = DB::table('users')->get(['id', 'name']);

        foreach ($users as $user) {
            DB::transaction(function () use ($user) {
                $workspaceId = DB::table('workspaces')->insertGetId([
                    'owner_id' => $user->id,
                    'name' => "{$user->name}'s Workspace",
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                DB::table('workspace_user')->insert([
                    'workspace_id' => $workspaceId,
                    'user_id' => $user->id,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                $boardIds = DB::table('boards')
                    ->where('user_id', $user->id)
                    ->whereNull('workspace_id')
                    ->pluck('id');

                DB::table('boards')
                    ->where('user_id', $user->id)
                    ->whereNull('workspace_id')
                    ->update(['workspace_id' => $workspaceId]);

                foreach ($boardIds as $boardId) {
                    DB::table('board_user')->insert([
                        'board_id' => $boardId,
                        'user_id' => $user->id,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            });
        }
    }

    public function down(): void
    {
        Schema::table('boards', function (Blueprint $table) {
            $table->dropConstrainedForeignId('workspace_id');
        });
    }
};

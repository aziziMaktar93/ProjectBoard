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

        $userIds = DB::table('boards')->whereNull('workspace_id')->distinct()->pluck('user_id');

        foreach ($userIds as $userId) {
            $user = DB::table('users')->find($userId);

            if (! $user) {
                continue;
            }

            $workspaceId = DB::table('workspaces')->insertGetId([
                'owner_id' => $userId,
                'name' => "{$user->name}'s Workspace",
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('workspace_user')->insert([
                'workspace_id' => $workspaceId,
                'user_id' => $userId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $boardIds = DB::table('boards')->where('user_id', $userId)->pluck('id');

            DB::table('boards')->where('user_id', $userId)->update(['workspace_id' => $workspaceId]);

            foreach ($boardIds as $boardId) {
                DB::table('board_user')->insert([
                    'board_id' => $boardId,
                    'user_id' => $userId,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        Schema::table('boards', function (Blueprint $table) {
            $table->dropConstrainedForeignId('workspace_id');
        });
    }
};

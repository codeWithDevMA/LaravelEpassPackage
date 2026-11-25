<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('epass_users')) {
            return;
        }

        if (! Schema::hasColumn('epass_users', 'uuid')) {
            Schema::table('epass_users', function (Blueprint $table): void {
                $table->uuid('uuid')->nullable()->unique();
            });
        }

        if (! Schema::hasColumn('epass_users', 'id')) {
            return;
        }

        DB::table('epass_users')
            ->whereNull('uuid')
            ->orWhere('uuid', '')
            ->orderBy('id')
            ->select(['id'])
            ->get()
            ->each(function ($user): void {
                DB::table('epass_users')
                    ->where('id', $user->id)
                    ->update(['uuid' => (string) Str::uuid()]);
            });
    }

    public function down(): void
    {
        // Intentionally preserved. UUIDs become external ePass identifiers.
    }
};

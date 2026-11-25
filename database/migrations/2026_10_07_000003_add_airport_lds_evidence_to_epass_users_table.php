<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('epass_users')) {
            return;
        }

        Schema::table('epass_users', function (Blueprint $table): void {
            if (!Schema::hasColumn('epass_users', 'sod_evidence')) {
                $table->longText('sod_evidence')->nullable();
            }
            if (!Schema::hasColumn('epass_users', 'dg1_evidence')) {
                $table->longText('dg1_evidence')->nullable();
            }
            if (!Schema::hasColumn('epass_users', 'dg11_evidence')) {
                $table->longText('dg11_evidence')->nullable();
            }
            if (!Schema::hasColumn('epass_users', 'sod_verification_record')) {
                $table->longText('sod_verification_record')->nullable();
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('epass_users')) {
            return;
        }

        Schema::table('epass_users', function (Blueprint $table): void {
            $columns = array_values(array_filter([
                Schema::hasColumn('epass_users', 'sod_evidence') ? 'sod_evidence' : null,
                Schema::hasColumn('epass_users', 'dg1_evidence') ? 'dg1_evidence' : null,
                Schema::hasColumn('epass_users', 'dg11_evidence') ? 'dg11_evidence' : null,
                Schema::hasColumn('epass_users', 'sod_verification_record') ? 'sod_verification_record' : null,
            ]));
            if ($columns !== []) {
                $table->dropColumn($columns);
            }
        });
    }
};

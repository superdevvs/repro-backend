<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shoots', function (Blueprint $table) {
            if (!Schema::hasColumn('shoots', 'shoot_notes')) {
                $table->text('shoot_notes')->nullable()->after('notes');
            }
            if (!Schema::hasColumn('shoots', 'company_notes')) {
                $table->text('company_notes')->nullable()->after('shoot_notes');
            }
            if (!Schema::hasColumn('shoots', 'photographer_notes')) {
                $table->text('photographer_notes')->nullable()->after('company_notes');
            }
            if (!Schema::hasColumn('shoots', 'editor_notes')) {
                $table->text('editor_notes')->nullable()->after('photographer_notes');
            }
        });
    }

    public function down(): void
    {
        Schema::table('shoots', function (Blueprint $table) {
            if (Schema::hasColumn('shoots', 'editor_notes')) {
                $table->dropColumn('editor_notes');
            }
            if (Schema::hasColumn('shoots', 'photographer_notes')) {
                $table->dropColumn('photographer_notes');
            }
            if (Schema::hasColumn('shoots', 'company_notes')) {
                $table->dropColumn('company_notes');
            }
            if (Schema::hasColumn('shoots', 'shoot_notes')) {
                $table->dropColumn('shoot_notes');
            }
        });
    }
};


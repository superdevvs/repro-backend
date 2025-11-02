<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shoots', function (Blueprint $table) {
            if (!Schema::hasColumn('shoots', 'service_category')) {
                $table->string('service_category')->nullable()->after('service_id');
            }
        });

        if (Schema::hasTable('dropbox_folders')) {
            Schema::table('dropbox_folders', function (Blueprint $table) {
                if (!Schema::hasColumn('dropbox_folders', 'service_category')) {
                    $table->string('service_category')->nullable()->after('folder_type');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('shoots', 'service_category')) {
            Schema::table('shoots', function (Blueprint $table) {
                $table->dropColumn('service_category');
            });
        }

        if (Schema::hasTable('dropbox_folders') && Schema::hasColumn('dropbox_folders', 'service_category')) {
            Schema::table('dropbox_folders', function (Blueprint $table) {
                $table->dropColumn('service_category');
            });
        }
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shoots', function (Blueprint $table) {
            $table->string('service_category')->nullable()->after('service_id');
            // Categories: 'P' (Photos), 'iGuide', 'Video'
        });

        Schema::table('dropbox_folders', function (Blueprint $table) {
            $table->string('service_category')->nullable()->after('folder_type');
        });
    }

    public function down(): void
    {
        Schema::table('shoots', function (Blueprint $table) {
            $table->dropColumn('service_category');
        });

        Schema::table('dropbox_folders', function (Blueprint $table) {
            $table->dropColumn('service_category');
        });
    }
};

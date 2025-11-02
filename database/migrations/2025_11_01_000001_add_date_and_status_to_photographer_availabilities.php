<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('photographer_availabilities', function (Blueprint $table) {
            if (!Schema::hasColumn('photographer_availabilities', 'date')) {
                $table->date('date')->nullable()->after('photographer_id');
            }
            if (!Schema::hasColumn('photographer_availabilities', 'status')) {
                $table->string('status')->default('available')->after('end_time');
            }
        });
    }

    public function down(): void
    {
        Schema::table('photographer_availabilities', function (Blueprint $table) {
            if (Schema::hasColumn('photographer_availabilities', 'status')) {
                $table->dropColumn('status');
            }
            if (Schema::hasColumn('photographer_availabilities', 'date')) {
                $table->dropColumn('date');
            }
        });
    }
};


<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
         Schema::table('users', function (Blueprint $table) {
            $table->string('username')->unique()->after('name');
            $table->string('phonenumber')->nullable()->after('email');
            $table->string('company_name')->nullable()->after('phonenumber');
            $table->string('role')->default('client')->after('company_name');
            $table->string('avatar')->nullable()->after('role');
            $table->text('bio')->nullable()->after('avatar');
            $table->string('account_status')->default('active')->after('bio'); // or use enum or boolean
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
        $table->dropColumn([
            'username',
            'phonenumber',
            'company_name',
            'role',
            'avatar',
            'bio',
            'account_status'
        ]);
    });
    }
};

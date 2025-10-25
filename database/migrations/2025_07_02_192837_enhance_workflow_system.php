<?php
// Note: renamed to run after create_shoots_table to satisfy FKs and alters
// Renamed again to run after create_shoot_files_table (2025_07_02_192836)

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Add workflow status to shoots table
        Schema::table('shoots', function (Blueprint $table) {
            $table->string('workflow_status')->default('booked')->after('status');
            // Workflow statuses: booked -> photos_uploaded -> editing_complete -> admin_verified -> completed
            $table->timestamp('photos_uploaded_at')->nullable();
            $table->timestamp('editing_completed_at')->nullable();
            $table->timestamp('admin_verified_at')->nullable();
            $table->foreignId('verified_by')->nullable()->constrained('users');
        });

        // Enhance shoot_files table for workflow tracking
        Schema::table('shoot_files', function (Blueprint $table) {
            $table->string('workflow_stage')->default('todo')->after('uploaded_by');
            // Stages: todo -> completed -> verified -> archived
            $table->string('dropbox_path')->nullable()->after('path');
            $table->string('dropbox_file_id')->nullable();
            $table->timestamp('moved_to_completed_at')->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->foreignId('verified_by')->nullable()->constrained('users');
            $table->text('verification_notes')->nullable();
        });

        // Create dropbox_folders table to track folder structure
        Schema::create('dropbox_folders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shoot_id')->constrained('shoots')->onDelete('cascade');
            $table->string('folder_type'); // 'todo', 'completed', 'final'
            $table->string('dropbox_path');
            $table->string('dropbox_folder_id')->nullable();
            $table->timestamps();
        });

        // Create workflow_logs table for audit trail
        Schema::create('workflow_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shoot_id')->constrained('shoots')->onDelete('cascade');
            $table->foreignId('user_id')->constrained('users');
            $table->string('action'); // 'photos_uploaded', 'moved_to_completed', 'verified', etc.
            $table->text('details')->nullable();
            $table->json('metadata')->nullable(); // Store additional data like file counts, etc.
            $table->timestamps();
        });

        // Create payments table for proper payment tracking
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shoot_id')->constrained('shoots')->onDelete('cascade');
            $table->decimal('amount', 10, 2);
            $table->string('currency', 3)->default('USD');
            $table->string('square_payment_id')->unique();
            $table->string('square_order_id')->nullable();
            $table->string('status'); // 'pending', 'completed', 'failed', 'refunded'
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::table('shoots', function (Blueprint $table) {
            $table->dropColumn([
                'workflow_status', 'photos_uploaded_at', 'editing_completed_at', 
                'admin_verified_at', 'verified_by'
            ]);
        });

        Schema::table('shoot_files', function (Blueprint $table) {
            $table->dropColumn([
                'workflow_stage', 'dropbox_path', 'dropbox_file_id',
                'moved_to_completed_at', 'verified_at', 'verified_by', 'verification_notes'
            ]);
        });

        Schema::dropIfExists('payments');
        Schema::dropIfExists('workflow_logs');
        Schema::dropIfExists('dropbox_folders');
    }
};

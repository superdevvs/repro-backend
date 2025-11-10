<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Shoot extends Model
{
    use HasFactory;

    protected $fillable = [
        'client_id',
        'photographer_id',
        'service_id',
        'service_category',
        'address',
        'city',
        'state',
        'zip',
        'scheduled_date',
        'time',
        'base_quote',
        'tax_amount',
        'total_quote',
        'payment_status',
        'payment_type',
        'notes',
        'shoot_notes',
        'company_notes',
        'photographer_notes',
        'editor_notes',
        'status',
        'workflow_status',
        'created_by',
        'photos_uploaded_at',
        'editing_completed_at',
        'admin_verified_at',
        'verified_by'
    ];

    protected $casts = [
        'scheduled_date' => 'date',
        'photos_uploaded_at' => 'datetime',
        'editing_completed_at' => 'datetime',
        'admin_verified_at' => 'datetime',
        'base_quote' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'total_quote' => 'decimal:2',
    ];

    // Workflow status constants
    const WORKFLOW_BOOKED = 'booked';
    const WORKFLOW_PHOTOS_UPLOADED = 'photos_uploaded';
    const WORKFLOW_EDITING_COMPLETE = 'editing_complete';
    const WORKFLOW_ADMIN_VERIFIED = 'admin_verified';
    const WORKFLOW_COMPLETED = 'completed';

    public function client()
    {
        return $this->belongsTo(User::class, 'client_id');
    }

    public function photographer()
    {
        return $this->belongsTo(User::class, 'photographer_id');
    }

    public function service()
    {
        return $this->belongsTo(Service::class);
    }

    public function verifiedBy()
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    public function files()
    {
        return $this->hasMany(ShootFile::class);
    }

    public function payments()
    {
        return $this->hasMany(Payment::class);
    }

    public function invoices()
    {
        return $this->belongsToMany(Invoice::class, 'invoice_shoot')->withTimestamps();
    }

    public function dropboxFolders()
    {
        return $this->hasMany(DropboxFolder::class);
    }

    public function workflowLogs()
    {
        return $this->hasMany(WorkflowLog::class);
    }

    // Helper methods
    public function getTotalPaidAttribute()
    {
        return $this->payments()->where('status', 'completed')->sum('amount');
    }

    public function getRemainingBalanceAttribute()
    {
        return $this->total_quote - $this->total_paid;
    }

    public function canUploadPhotos()
    {
        // Allow raw uploads when newly booked/scheduled, and allow additional raw uploads
        // after initial upload until admin moves the workflow forward.
        return in_array($this->workflow_status, [
            self::WORKFLOW_BOOKED,
            self::WORKFLOW_PHOTOS_UPLOADED,
        ]);
    }

    public function canMoveToCompleted()
    {
        return $this->workflow_status === self::WORKFLOW_PHOTOS_UPLOADED;
    }

    public function canVerify()
    {
        return $this->workflow_status === self::WORKFLOW_EDITING_COMPLETE;
    }

    public function updateWorkflowStatus($status, $userId = null)
    {
        $oldStatus = $this->workflow_status;
        $this->workflow_status = $status;

        // Set timestamps based on status
        switch ($status) {
            case self::WORKFLOW_PHOTOS_UPLOADED:
                $this->photos_uploaded_at = now();
                break;
            case self::WORKFLOW_EDITING_COMPLETE:
                $this->editing_completed_at = now();
                break;
            case self::WORKFLOW_ADMIN_VERIFIED:
                $this->admin_verified_at = now();
                $this->verified_by = $userId;
                break;
        }

        $this->save();

        // Log the workflow change
        $this->workflowLogs()->create([
            'user_id' => $userId ?? auth()->id(),
            'action' => "status_changed_to_{$status}",
            'details' => "Workflow status changed from {$oldStatus} to {$status}",
            'metadata' => [
                'old_status' => $oldStatus,
                'new_status' => $status,
                'timestamp' => now()->toISOString()
            ]
        ]);
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Shoot extends Model
{
    use HasFactory;

    protected $fillable = [
        'client_id',
        'scheduled_date',
        'time',
        'address',
        'city',
        'state',
        'zip',
        'photographer_id',
        'service_id',
        'notes',
        'bypass_payment',
        'send_notification',
        'base_quote',
        'tax_amount',
        'total_quote',
        'payment_status',
        'status',
        'created_by',
    ];

    // ✅ Client is a user
    public function client()
    {
        return $this->belongsTo(User::class, 'client_id');
    }

    // ✅ Photographer is also a user
    public function photographer()
    {
        return $this->belongsTo(User::class, 'photographer_id');
    }

    // ✅ Service relationship (if you have a services table)
    public function service()
    {
        return $this->belongsTo(Service::class);
    }

    /**
     * Get all files for the shoot.
     */
    public function files(): HasMany
    {
        return $this->hasMany(ShootFile::class);
    }

    /**
     * Get only image files for the shoot.
     */
    public function images(): HasMany
    {
        return $this->hasMany(ShootFile::class)->where('file_type', 'like', 'image/%');
    }

    /**
     * Get only video files for the shoot.
     */
    public function videos(): HasMany
    {
        return $this->hasMany(ShootFile::class)->where('file_type', 'like', 'video/%');
    }

    /**
     * Get the full address.
     */
    public function getFullAddressAttribute(): string
    {
        return "{$this->address}, {$this->city}, {$this->state} {$this->zip}";
    }

    /**
     * Check if the shoot has any files uploaded.
     */
    public function hasFiles(): bool
    {
        return $this->files()->count() > 0;
    }

    /**
     * Get the total number of files.
     */
    public function getFileCountAttribute(): int
    {
        return $this->files()->count();
    }
}

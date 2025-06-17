<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

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
}

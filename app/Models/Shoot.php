<?php

namespace App\Models;

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
}
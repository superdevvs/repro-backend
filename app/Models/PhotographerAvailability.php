<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class PhotographerAvailability extends Model
{
    use HasFactory;

    protected $fillable = [
        'photographer_id',
        'date',
        'day_of_week',
        'start_time',
        'end_time',
        'status',
    ];

    public function photographer()
    {
        return $this->belongsTo(User::class, 'photographer_id');
    }
}

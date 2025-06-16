<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PhotographerAvailability extends Model
{
    use HasFactory;

    protected $fillable = [
        'photographer_id',
        'day_of_week',
        'start_time',
        'end_time',
    ];

    public function photographer()
    {
        return $this->belongsTo(User::class, 'photographer_id');
    }
}

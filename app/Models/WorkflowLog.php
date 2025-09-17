<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WorkflowLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'shoot_id',
        'user_id',
        'action',
        'details',
        'metadata'
    ];

    protected $casts = [
        'metadata' => 'array',
    ];

    public function shoot()
    {
        return $this->belongsTo(Shoot::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
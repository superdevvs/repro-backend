<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DropboxFolder extends Model
{
    use HasFactory;

    protected $fillable = [
        'shoot_id',
        'folder_type',
        'dropbox_path',
        'dropbox_folder_id'
    ];

    // Folder type constants
    const TYPE_TODO = 'todo';
    const TYPE_COMPLETED = 'completed';
    const TYPE_FINAL = 'final';

    public function shoot()
    {
        return $this->belongsTo(Shoot::class);
    }
}
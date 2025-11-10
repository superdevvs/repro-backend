<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Invoice extends Model
{
    use HasFactory;

    protected $fillable = [
        'shoot_id',
        'client_id',
        'invoice_number',
        'issue_date',
        'due_date',
        'subtotal',
        'tax',
        'total',
        'status',
        'notes',
        'paid_at',
    ];

    protected $casts = [
        'issue_date' => 'date',
        'due_date' => 'date',
        'subtotal' => 'decimal:2',
        'tax' => 'decimal:2',
        'total' => 'decimal:2',
        'paid_at' => 'datetime',
    ];

    public function shoot()
    {
        return $this->belongsTo(Shoot::class);
    }

    public function client()
    {
        return $this->belongsTo(User::class, 'client_id');
    }

    public function items()
    {
        return $this->hasMany(InvoiceItem::class);
    }

    public function payments()
    {
        return $this->hasMany(Payment::class);
    }

    public function totalPaid(): float
    {
        if ($this->getAttribute('total_paid_amount') !== null) {
            return (float) $this->getAttribute('total_paid_amount');
        }

        if ($this->relationLoaded('payments')) {
            return (float) $this->payments
                ->where('status', Payment::STATUS_COMPLETED)
                ->sum('amount');
        }

        return (float) $this->payments()
            ->where('status', Payment::STATUS_COMPLETED)
            ->sum('amount');
    }

    public function balanceDue(): float
    {
        return max((float) $this->total - $this->totalPaid(), 0);
    }

    public function isPastDue(): bool
    {
        $dueDate = $this->due_date instanceof Carbon ? $this->due_date : ($this->due_date ? Carbon::parse($this->due_date) : null);

        return $dueDate !== null
            && $dueDate->isPast()
            && $this->balanceDue() > 0;
    }
}

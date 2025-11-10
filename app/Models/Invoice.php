<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Carbon\Carbon;

class Invoice extends Model
{
    use HasFactory;

    public const STATUS_DRAFT = 'draft';
    public const STATUS_SENT = 'sent';
    public const STATUS_PAID = 'paid';

    public const ROLE_CLIENT = 'client';
    public const ROLE_PHOTOGRAPHER = 'photographer';

    protected $fillable = [
        'user_id',
        'role',
        'period_start',
        'period_end',
        'charges_total',
        'payments_total',
        'balance_due',
        'status',
        'sent_at',
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
        'period_start' => 'date',
        'period_end' => 'date',
        'charges_total' => 'decimal:2',
        'payments_total' => 'decimal:2',
        'balance_due' => 'decimal:2',
        'sent_at' => 'datetime',
        'paid_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function items()
    {
        return $this->hasMany(InvoiceItem::class);
    }

    public function scopeForRole($query, string $role)
    {
        return $query->where('role', $role);
    }

    public function scopeWithStatus($query, string $status)
    {
        return $query->where('status', $status);
    }

    public function refreshTotals(): void
    {
        $items = $this->items()->get();

        $charges = $items->where('type', InvoiceItem::TYPE_CHARGE)->sum('total_amount');
        $payments = $items->where('type', InvoiceItem::TYPE_PAYMENT)->sum('total_amount');

        $this->charges_total = $charges;
        $this->payments_total = $payments;
        $this->balance_due = $charges - $payments;

        $this->save();
    }

    public function markSent(?Carbon $sentAt = null): void
    {
        $this->status = self::STATUS_SENT;
        $this->sent_at = $sentAt ?? now();
        $this->save();
    }

    public function markPaid(?Carbon $paidAt = null): void
    {
        $this->status = self::STATUS_PAID;
        $this->paid_at = $paidAt ?? now();
        $this->balance_due = 0;
        $this->save();
    }

    public function getIsPaidAttribute(): bool
    {
        return $this->status === self::STATUS_PAID;
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

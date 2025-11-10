<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

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
    }
}

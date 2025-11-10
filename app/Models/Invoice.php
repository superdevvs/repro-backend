<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Invoice extends Model
{
    use HasFactory;

    protected $fillable = [
        'photographer_id',
        'sales_rep_id',
        'billing_period_start',
        'billing_period_end',
        'total_amount',
        'amount_paid',
        'is_sent',
        'is_paid',
        'paid_at',
    ];

    protected $casts = [
        'billing_period_start' => 'date',
        'billing_period_end' => 'date',
        'total_amount' => 'decimal:2',
        'amount_paid' => 'decimal:2',
        'is_sent' => 'boolean',
        'is_paid' => 'boolean',
        'paid_at' => 'datetime',
    ];

    public function photographer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'photographer_id');
    }

    public function salesRep(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sales_rep_id');
    }

    public function shoots(): BelongsToMany
    {
        return $this->belongsToMany(Shoot::class, 'invoice_shoot')->withTimestamps();
    }

    public function markAsPaid(?string $paidAt = null, ?float $amountPaid = null): void
    {
        $this->forceFill([
            'is_paid' => true,
            'paid_at' => $paidAt ? Carbon::parse($paidAt) : now(),
            'amount_paid' => $amountPaid ?? $this->total_amount,
        ])->save();
    }
}

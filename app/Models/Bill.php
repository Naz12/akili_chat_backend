<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Carbon\Carbon;

class Bill extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'subscription_id',
        'payment_id',
        'type',
        'status',
        'amount',
        'currency',
        'due_date',
        'paid_at',
        'description',
        'metadata',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'due_date' => 'datetime',
        'paid_at' => 'datetime',
        'metadata' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get the user that owns the bill.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the subscription associated with the bill.
     */
    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }

    /**
     * Get the payment associated with the bill.
     */
    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    /**
     * Scope: Filter by status.
     */
    public function scopeStatus($query, string $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Scope: Filter by type.
     */
    public function scopeType($query, string $type)
    {
        return $query->where('type', $type);
    }

    /**
     * Scope: Get pending bills.
     */
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    /**
     * Scope: Get overdue bills.
     */
    public function scopeOverdue($query)
    {
        return $query->where('status', 'pending')
            ->where('due_date', '<', now());
    }

    /**
     * Scope: Get paid bills.
     */
    public function scopePaid($query)
    {
        return $query->where('status', 'paid');
    }

    /**
     * Check if bill is overdue.
     */
    public function isOverdue(): bool
    {
        return $this->status === 'pending' && $this->due_date < now();
    }

    /**
     * Check if bill can be paid.
     */
    public function canBePaid(): bool
    {
        return $this->status === 'pending' && !$this->isOverdue();
    }

    /**
     * Mark bill as paid.
     */
    public function markAsPaid(Payment $payment): void
    {
        $this->update([
            'status' => 'paid',
            'payment_id' => $payment->id,
            'paid_at' => now(),
        ]);
    }

    /**
     * Mark bill as overdue.
     */
    public function markAsOverdue(): void
    {
        if ($this->status === 'pending' && $this->due_date < now()) {
            $this->update(['status' => 'overdue']);
        }
    }
}

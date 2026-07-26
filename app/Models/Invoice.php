<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Invoice extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'organization_id', 'customer_id', 'created_by', 'invoice_no', 'status',
        'issue_date', 'due_date', 'subtotal', 'tax_rate', 'tax_amount',
        'tax_mechanism', 'ppn_dipungut_wapu', 'ssp_received',
        'discount', 'total', 'paid_amount', 'notes', 'sent_at', 'paid_at',
    ];

    protected $casts = [
        'issue_date'        => 'date',
        'due_date'          => 'date',
        'sent_at'           => 'datetime',
        'paid_at'           => 'datetime',
        'ssp_received'      => 'boolean',
        'subtotal'          => 'decimal:2',
        'tax_amount'        => 'decimal:2',
        'ppn_dipungut_wapu' => 'decimal:2',
        'total'             => 'decimal:2',
        'paid_amount'       => 'decimal:2',
    ];

    public function customer(): BelongsTo    { return $this->belongsTo(Customer::class); }
    public function creator(): BelongsTo     { return $this->belongsTo(User::class, 'created_by'); }
    public function organization(): BelongsTo { return $this->belongsTo(Organization::class); }
    public function items(): HasMany         { return $this->hasMany(InvoiceItem::class); }
    public function approvals(): HasMany     { return $this->hasMany(InvoiceApproval::class); }
    public function journalEntries(): HasMany {
        return $this->hasMany(JournalEntry::class, 'ref_id')->where('ref_type', 'invoice');
    }

    public function getBalanceDueAttribute(): float
    {
        return $this->total - $this->paid_amount;
    }

    public function getCashExpectedAttribute(): float
    {
        return $this->tax_mechanism === 'wapu'
            ? $this->total - $this->ppn_dipungut_wapu
            : $this->total;
    }

    public function isEditable(): bool { return $this->status === 'draft'; }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class JournalLine extends Model
{
    use HasUuids;

    public $timestamps  = false;
    protected $fillable = ['journal_entry_id', 'account_id', 'debit', 'credit', 'memo'];
    protected $casts    = ['debit' => 'decimal:2', 'credit' => 'decimal:2'];

    public function journalEntry(): BelongsTo { return $this->belongsTo(JournalEntry::class); }
    public function account(): BelongsTo      { return $this->belongsTo(Account::class); }
}

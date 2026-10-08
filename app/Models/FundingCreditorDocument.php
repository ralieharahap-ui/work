<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FundingCreditorDocument extends Model
{
    use HasUuids;

    /** Jenis dokumen => [label, boleh multi-upload] */
    public const TYPES = [
        'kontrak'        => ['Kontrak/Perjanjian', true],
        'bukti_transfer' => ['Bukti Transfer', false],
        'foto_jurnal'    => ['Foto Jurnal', true],
        'kwitansi'       => ['Kwitansi/Tanda Terima Pembayaran', true],
    ];

    protected $fillable = [
        'funding_creditor_id', 'doc_type', 'path', 'original_name', 'mime', 'size', 'uploaded_by',
    ];

    public function creditor(): BelongsTo { return $this->belongsTo(FundingCreditor::class, 'funding_creditor_id'); }
}

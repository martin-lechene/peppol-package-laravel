<?php

namespace PeppolPackage\EInvoices\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InvoiceTransmission extends Model
{
    protected $fillable = [
        'invoice_id',
        'type',
        'gateway',
        'status',
        'response_code',
        'document_id',
        'payload',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
        ];
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }
}

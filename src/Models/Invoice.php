<?php

namespace PeppolPackage\EInvoices\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Invoice extends Model
{
    protected $fillable = [
        'organization_id',
        'invoice_number',
        'issue_date',
        'due_date',
        'currency',
        'type',
        'format',
        'sender_siret',
        'recipient_siret',
        'status',
        'amount_subtotal',
        'amount_tax',
        'amount_total',
        'xml_content',
    ];

    protected function casts(): array
    {
        return [
            'issue_date' => 'date',
            'due_date' => 'date',
            'amount_subtotal' => 'decimal:2',
            'amount_tax' => 'decimal:2',
            'amount_total' => 'decimal:2',
        ];
    }

    public function lineItems(): HasMany
    {
        return $this->hasMany(InvoiceLineItem::class);
    }

    public function transmissions(): HasMany
    {
        return $this->hasMany(InvoiceTransmission::class);
    }

    public function calculateTotals(): void
    {
        $this->loadMissing('lineItems');

        $subtotal = 0.0;
        $tax = 0.0;

        foreach ($this->lineItems as $line) {
            $lineNet = (float) $line->quantity * (float) $line->unit_price;
            $subtotal += $lineNet;
            $tax += $lineNet * ((float) $line->tax_percent / 100.0);
        }

        $this->amount_subtotal = round($subtotal, 2);
        $this->amount_tax = round($tax, 2);
        $this->amount_total = round($subtotal + $tax, 2);
        $this->save();
    }

    public function recordTransmission(array $data): InvoiceTransmission
    {
        return $this->transmissions()->create($data);
    }
}

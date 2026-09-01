<?php

namespace PeppolPackage\EInvoices\Facades;

use Illuminate\Support\Facades\Facade;
use PeppolPackage\EInvoices\InvoiceManager;
use PeppolPackage\EInvoices\Models\Invoice as InvoiceModel;
use PeppolPackage\EInvoices\Support\TransmissionResult;

/**
 * @method static string generate(InvoiceModel $invoice, string $format = 'PEPPOL_BIS')
 * @method static TransmissionResult transmit(InvoiceModel $invoice)
 * @method static \PeppolPackage\EInvoices\Validation\ValidationResult validate(string $xml)
 * @method static \PeppolPackage\EInvoices\Validation\ValidationResult validateSigned(string $xml)
 * @method static string sign(string $xml, ?string $certificate = null, ?string $privateKey = null, ?string $keyPassword = null)
 *
 * @see InvoiceManager
 */
class Invoice extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'e-invoices';
    }
}

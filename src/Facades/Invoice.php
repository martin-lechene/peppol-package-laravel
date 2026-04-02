<?php

namespace PeppolPackage\EInvoices\Facades;

use Illuminate\Support\Facades\Facade;
use PeppolPackage\EInvoices\InvoiceManager;
use PeppolPackage\EInvoices\Models\Invoice as InvoiceModel;
use PeppolPackage\EInvoices\Support\TransmissionResult;

/**
 * @method static string generate(InvoiceModel $invoice, string $format = 'PEPPOL_BIS')
 * @method static TransmissionResult transmit(InvoiceModel $invoice)
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

<?php

namespace PeppolPackage\EInvoices\Support;

use PeppolPackage\EInvoices\Models\Invoice;

/**
 * Minimal UBL 2.1 invoice skeleton for demos — not a full EN16931 validator pass.
 */
class PeppolBisXmlBuilder
{
    public static function build(Invoice $invoice): string
    {
        $invoice->loadMissing('lineItems');

        $esc = static fn (?string $s): string => htmlspecialchars((string) $s, ENT_XML1 | ENT_QUOTES, 'UTF-8');

        $linesXml = '';
        foreach ($invoice->lineItems as $i => $line) {
            $n = $i + 1;
            $qty = (float) $line->quantity;
            $price = (float) $line->unit_price;
            $unitCode = self::unEceUnitCode((string) $line->unit);
            $linesXml .= <<<XML
            <cac:InvoiceLine>
                <cbc:ID>{$n}</cbc:ID>
                <cbc:InvoicedQuantity unitCode="{$esc($unitCode)}">{$qty}</cbc:InvoicedQuantity>
                <cbc:LineExtensionAmount currencyID="{$esc($invoice->currency)}">{$esc((string) ($qty * $price))}</cbc:LineExtensionAmount>
                <cac:Item>
                    <cbc:Description>{$esc($line->description)}</cbc:Description>
                </cac:Item>
                <cac:Price>
                    <cbc:PriceAmount currencyID="{$esc($invoice->currency)}">{$price}</cbc:PriceAmount>
                </cac:Price>
            </cac:InvoiceLine>

            XML;
        }

        $issue = $invoice->issue_date?->format('Y-m-d') ?? date('Y-m-d');
        $due = $invoice->due_date?->format('Y-m-d') ?? date('Y-m-d');

        $senderScheme = PeppolParticipantScheme::schemeForPartyId($invoice->sender_siret);
        $recipientScheme = PeppolParticipantScheme::schemeForPartyId($invoice->recipient_siret);

        return <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<Invoice xmlns="urn:oasis:names:specification:ubl:schema:xsd:Invoice-2"
         xmlns:cac="urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2"
         xmlns:cbc="urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2">
    <cbc:CustomizationID>urn:cen.eu:en16931:2017#compliant#urn:fdc:peppol.eu:2017:poacc:billing:3.0</cbc:CustomizationID>
    <cbc:ProfileID>urn:fdc:peppol.eu:2017:poacc:billing:01:1.0</cbc:ProfileID>
    <cbc:ID>{$esc($invoice->invoice_number)}</cbc:ID>
    <cbc:IssueDate>{$issue}</cbc:IssueDate>
    <cbc:DueDate>{$due}</cbc:DueDate>
    <cbc:InvoiceTypeCode>380</cbc:InvoiceTypeCode>
    <cbc:DocumentCurrencyCode>{$esc($invoice->currency)}</cbc:DocumentCurrencyCode>
    <cbc:BuyerReference>{$esc($invoice->recipient_siret)}</cbc:BuyerReference>
    <cac:AccountingSupplierParty>
        <cac:Party>
            <cac:PartyIdentification>
                <cbc:ID schemeID="{$esc($senderScheme)}">{$esc($invoice->sender_siret)}</cbc:ID>
            </cac:PartyIdentification>
        </cac:Party>
    </cac:AccountingSupplierParty>
    <cac:AccountingCustomerParty>
        <cac:Party>
            <cac:PartyIdentification>
                <cbc:ID schemeID="{$esc($recipientScheme)}">{$esc($invoice->recipient_siret)}</cbc:ID>
            </cac:PartyIdentification>
        </cac:Party>
    </cac:AccountingCustomerParty>
    <cac:LegalMonetaryTotal>
        <cbc:LineExtensionAmount currencyID="{$esc($invoice->currency)}">{$esc((string) $invoice->amount_subtotal)}</cbc:LineExtensionAmount>
        <cbc:TaxExclusiveAmount currencyID="{$esc($invoice->currency)}">{$esc((string) $invoice->amount_subtotal)}</cbc:TaxExclusiveAmount>
        <cbc:TaxInclusiveAmount currencyID="{$esc($invoice->currency)}">{$esc((string) $invoice->amount_total)}</cbc:TaxInclusiveAmount>
        <cbc:PayableAmount currencyID="{$esc($invoice->currency)}">{$esc((string) $invoice->amount_total)}</cbc:PayableAmount>
    </cac:LegalMonetaryTotal>
{$linesXml}</Invoice>

XML;
    }

    private static function unEceUnitCode(string $unit): string
    {
        $u = strtoupper(trim($unit));

        return match ($u) {
            'HOUR', 'HRS', 'HR' => 'HUR',
            'DAY' => 'DAY',
            'PIECE', 'PC', 'PCE' => 'C62',
            'KGM', 'KG' => 'KGM',
            default => strlen($u) <= 3 ? $u : 'C62',
        };
    }
}

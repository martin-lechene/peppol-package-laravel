<?php

namespace PeppolPackage\EInvoices\Tests\Feature;

use PeppolPackage\EInvoices\Facades\Invoice;
use PeppolPackage\EInvoices\Tests\TestCase;

final class InvoiceManagerWorkflowTest extends TestCase
{
    public function test_validates_the_bundled_ubl_fixture(): void
    {
        $xml = file_get_contents(__DIR__.'/../fixtures/en16931-valid.xml');

        $result = Invoice::validate($xml);

        $this->assertTrue($result->passes(), implode("\n", $result->errors));
    }

    public function test_signs_and_verifies_generated_invoice_xml(): void
    {
        $invoice = $this->makeInvoice();

        $xml = Invoice::generate($invoice);
        $this->assertNotEmpty($xml);

        $signed = Invoice::sign($xml, $this->signerCertificate(), $this->signerPrivateKey());

        $this->assertNotEmpty($signed);
        $this->assertNotEmpty($this->verifyXadesSignature($signed));
    }

    public function test_facade_sign_without_credentials_throws(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        Invoice::sign('<?xml version="1.0"?><i/>');
    }
}

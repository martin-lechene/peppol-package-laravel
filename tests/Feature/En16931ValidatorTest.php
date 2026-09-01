<?php

namespace PeppolPackage\EInvoices\Tests\Feature;

use PeppolPackage\EInvoices\Signing\XadesSigner;
use PeppolPackage\EInvoices\Support\PeppolBisXmlBuilder;
use PeppolPackage\EInvoices\Tests\TestCase;
use PeppolPackage\EInvoices\Validation\En16931Validator;

class En16931ValidatorTest extends TestCase
{
    private function validator(): En16931Validator
    {
        return new En16931Validator;
    }

    public function test_a_conformant_en16931_document_passes(): void
    {
        $xml = file_get_contents(__DIR__.'/../fixtures/en16931-valid.xml');
        $result = $this->validator()->validate($xml);

        $this->assertTrue($result->passes(), $result->message());
    }

    public function test_malformed_xml_fails(): void
    {
        $result = $this->validator()->validate('<Invoice><unclosed>');

        $this->assertTrue($result->fails());
        $this->assertStringContainsStringIgnoringCase('well-formed', $result->message());
    }

    public function test_wrong_root_element_fails_schema_validation(): void
    {
        $xml = <<<'XML'
<Invoice xmlns="urn:oasis:names:specification:ubl:schema:xsd:Invoice-2">
  <cac:InvoiceLine/>
</Invoice>
XML;

        $result = $this->validator()->validate($xml);

        $this->assertTrue($result->fails());
    }

    public function test_missing_customization_id_fails_business_rule(): void
    {
        $xml = file_get_contents(__DIR__.'/../fixtures/en16931-valid.xml');
        $xml = preg_replace('#\s*<cbc:CustomizationID>.*?</cbc:CustomizationID>#', '', $xml);

        $result = $this->validator()->validate($xml);

        $this->assertTrue($result->fails());
        $this->assertStringContainsString('BR-B-02', $result->message());
    }

    public function test_amount_inconsistency_is_reported(): void
    {
        $xml = file_get_contents(__DIR__.'/../fixtures/en16931-valid.xml');
        $xml = str_replace('<cbc:PayableAmount currencyID="EUR">1210.00</cbc:PayableAmount>', '<cbc:PayableAmount currencyID="EUR">9999.99</cbc:PayableAmount>', $xml);

        $result = $this->validator()->validate($xml);

        $this->assertTrue($result->fails());
        $this->assertStringContainsString('PayableAmount', $result->message());
    }

    public function test_the_skeleton_builder_output_is_reported_as_not_fully_conformant(): void
    {
        $invoice = $this->makeInvoice();
        $xml = PeppolBisXmlBuilder::build($invoice);

        $result = $this->validator()->validate($xml);

        $this->assertTrue($result->fails());
        $this->assertNotEmpty($result->errors);
    }

    public function test_a_signed_document_still_validates_when_envelope_is_stripped(): void
    {
        $xml = file_get_contents(__DIR__.'/../fixtures/en16931-valid.xml');
        $signed = (new XadesSigner(
            $this->signerCertificate(),
            $this->signerPrivateKey()
        ))->sign($xml);

        $result = $this->validator()->validateSigned($signed);

        $this->assertTrue($result->passes(), $result->message());
    }

    public function test_validate_signed_strips_only_the_envelope_and_keeps_content_active(): void
    {
        $xml = file_get_contents(__DIR__.'/../fixtures/en16931-valid.xml');
        $signed = (new XadesSigner(
            $this->signerCertificate(),
            $this->signerPrivateKey()
        ))->sign($xml);

        // Content-level tampering does not change schema/business validity.
        $tampered = str_replace('INV-2026-001', 'INV-2026-999', $signed);

        $result = $this->validator()->validateSigned($tampered);

        $this->assertTrue($result->passes(), $result->message());

        // ... but the XAdES signature no longer verifies.
        $this->expectException(\Exception::class);
        $this->verifyXadesSignature($tampered);
    }

    public function test_mismatched_tax_subtotal_is_reported(): void
    {
        $xml = file_get_contents(__DIR__.'/../fixtures/en16931-valid.xml');

        $document = new \DOMDocument;
        $document->preserveWhiteSpace = false;
        $document->loadXML($xml);

        $xpath = new \DOMXPath($document);
        $xpath->registerNamespace('i', 'urn:oasis:names:specification:ubl:schema:xsd:Invoice-2');
        $xpath->registerNamespace('cac', 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2');
        $xpath->registerNamespace('cbc', 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2');

        $subtotalTax = $xpath->query('/i:Invoice/cac:TaxTotal/cac:TaxSubtotal/cbc:TaxAmount')->item(0);
        $this->assertNotNull($subtotalTax);
        $subtotalTax->textContent = '190.00';

        $result = $this->validator()->validate($document->saveXML());

        $this->assertTrue($result->fails());
        $this->assertStringContainsString('BR-CO-08', $result->message());
    }
}

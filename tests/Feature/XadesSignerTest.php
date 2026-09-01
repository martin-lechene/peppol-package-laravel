<?php

namespace PeppolPackage\EInvoices\Tests\Feature;

use DOMXPath;
use PeppolPackage\EInvoices\Signing\XadesSigner;
use PeppolPackage\EInvoices\Tests\TestCase;
use RobRichards\XMLSecLibs\XMLSecurityKey;

class XadesSignerTest extends TestCase
{
    private const XADES_NS = 'http://uri.etsi.org/01903/v1.3.2#';

    private const EXT_NS = 'urn:oasis:names:specification:ubl:schema:xsd:CommonExtensionComponents-2';

    private const DS_NS = 'http://www.w3.org/2000/09/xmldsig#';

    private function signer(): XadesSigner
    {
        return new XadesSigner($this->signerCertificate(), $this->signerPrivateKey());
    }

    private function xpath(\DOMDocument $document): DOMXPath
    {
        $xpath = new DOMXPath($document);
        $xpath->registerNamespace('i', 'urn:oasis:names:specification:ubl:schema:xsd:Invoice-2');
        $xpath->registerNamespace('ext', self::EXT_NS);
        $xpath->registerNamespace('ds', self::DS_NS);
        $xpath->registerNamespace('xades', self::XADES_NS);

        return $xpath;
    }

    public function test_signature_is_placed_inside_ublextensions(): void
    {
        $xml = file_get_contents(__DIR__.'/../fixtures/en16931-valid.xml');
        $signed = $this->signer()->sign($xml);

        $document = new \DOMDocument;
        $document->loadXML($signed);
        $xpath = $this->xpath($document);

        $signature = $xpath->query('/i:Invoice/ext:UBLExtensions/ext:UBLExtension/ext:ExtensionContent/ds:Signature');
        $this->assertSame(1, $signature->length);
    }

    public function test_signature_references_the_invoice_root_by_id(): void
    {
        $xml = file_get_contents(__DIR__.'/../fixtures/en16931-valid.xml');
        $signed = $this->signer()->sign($xml);

        $document = new \DOMDocument;
        $document->loadXML($signed);
        $xpath = $this->xpath($document);

        $root = $document->documentElement;
        $rootId = $root->getAttributeNS('http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-wssecurity-utility-1.0.xsd', 'Id');
        $this->assertNotEmpty($rootId);

        $reference = $xpath->query('/i:Invoice/ext:UBLExtensions/ext:UBLExtension/ext:ExtensionContent/ds:Signature/ds:SignedInfo/ds:Reference')->item(0);
        $this->assertSame('#'.$rootId, $reference->getAttribute('URI'));

        $method = $xpath->query('/i:Invoice/ext:UBLExtensions/ext:UBLExtension/ext:ExtensionContent/ds:Signature/ds:SignedInfo/ds:SignatureMethod')->item(0);
        $this->assertSame(XMLSecurityKey::RSA_SHA256, $method->getAttribute('Algorithm'));
    }

    public function test_signature_verifies_against_the_signing_certificate(): void
    {
        $xml = file_get_contents(__DIR__.'/../fixtures/en16931-valid.xml');
        $signed = $this->signer()->sign($xml);

        $validated = $this->verifyXadesSignature($signed);

        $this->assertNotEmpty($validated);
        $first = reset($validated);
        $this->assertSame('Invoice', $first->localName);
    }

    public function test_tampering_invalidates_the_signature(): void
    {
        $xml = file_get_contents(__DIR__.'/../fixtures/en16931-valid.xml');
        $signed = $this->signer()->sign($xml);

        $tampered = str_replace('INV-2026-001', 'INV-2026-999', $signed);

        $this->expectException(\Exception::class);
        $this->verifyXadesSignature($tampered);
    }

    public function test_xades_signing_time_is_present(): void
    {
        $xml = file_get_contents(__DIR__.'/../fixtures/en16931-valid.xml');
        $signed = $this->signer()->sign($xml);

        $document = new \DOMDocument;
        $document->loadXML($signed);
        $xpath = $this->xpath($document);

        $signingTime = $xpath->query('/i:Invoice/ext:UBLExtensions/ext:UBLExtension/ext:ExtensionContent/ds:Object/xades:QualifyingProperties/xades:SignedProperties/xades:SignedSignatureProperties/xades:SigningTime')->item(0);
        $this->assertNotNull($signingTime);
        $this->assertMatchesRegularExpression(
            '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z$/',
            trim($signingTime->textContent)
        );
    }

    public function test_non_invoice_document_is_rejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->signer()->sign('<CreditNote xmlns="urn:oasis:names:specification:ubl:schema:xsd:CreditNote-2"/>');
    }

    public function test_signing_certificate_presents_cert_digest_and_issuer_serial(): void
    {
        $xml = file_get_contents(__DIR__.'/../fixtures/en16931-valid.xml');
        $signed = $this->signer()->sign($xml);

        $document = new \DOMDocument;
        $document->loadXML($signed);
        $xpath = $this->xpath($document);

        $base = '/i:Invoice/ext:UBLExtensions/ext:UBLExtension/ext:ExtensionContent/ds:Object/xades:QualifyingProperties/xades:SignedProperties/xades:SignedSignatureProperties/xades:SigningCertificate/xades:Cert';

        $digestValue = $xpath->query($base.'/xades:CertDigest/ds:DigestValue')->item(0);
        $this->assertNotNull($digestValue);

        $expected = base64_encode(hash('sha256', $this->signerCertificateDer(), true));
        $this->assertSame($expected, trim($digestValue->textContent));

        $issuerName = $xpath->query($base.'/xades:IssuerSerial/ds:X509IssuerName')->item(0);
        $serial = $xpath->query($base.'/xades:IssuerSerial/ds:X509SerialNumber')->item(0);
        $this->assertNotNull($issuerName);
        $this->assertNotSame('', trim($issuerName->textContent));
        $this->assertNotNull($serial);
        $this->assertSame($this->signerCertificateSerialHex(), trim($serial->textContent));
    }

    public function test_signature_policy_identifier_is_explicit(): void
    {
        $xml = file_get_contents(__DIR__.'/../fixtures/en16931-valid.xml');
        $signed = $this->signer()->sign($xml);

        $document = new \DOMDocument;
        $document->loadXML($signed);
        $xpath = $this->xpath($document);

        $identifier = $xpath->query('/i:Invoice/ext:UBLExtensions/ext:UBLExtension/ext:ExtensionContent/ds:Object/xades:QualifyingProperties/xades:SignedProperties/xades:SignedSignatureProperties/xades:SignaturePolicyIdentifier/xades:SignaturePolicyId/xades:SigPolicyId/xades:Identifier')->item(0);
        $this->assertNotNull($identifier);
        $this->assertSame('urn:peppol:policy:authorization:1.0', trim($identifier->textContent));
    }

    public function test_data_object_format_references_invoice_root(): void
    {
        $xml = file_get_contents(__DIR__.'/../fixtures/en16931-valid.xml');
        $signed = $this->signer()->sign($xml);

        $document = new \DOMDocument;
        $document->loadXML($signed);
        $xpath = $this->xpath($document);

        $rootId = $document->documentElement->getAttributeNS(
            'http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-wssecurity-utility-1.0.xsd',
            'Id'
        );

        $format = $xpath->query('/i:Invoice/ext:UBLExtensions/ext:UBLExtension/ext:ExtensionContent/ds:Object/xades:QualifyingProperties/xades:SignedProperties/xades:SignedDataObjectProperties/xades:DataObjectFormat')->item(0);
        $this->assertNotNull($format);
        $this->assertSame('#'.$rootId, $format->getAttribute('ObjectReference'));
        $this->assertSame('application/xml', trim($xpath->query('xades:MimeType', $format)->item(0)->textContent));
    }

    private function signerCertificateDer(): string
    {
        $lines = array_map('trim', preg_split('/\r?\n/', $this->signerCertificate()) ?: []);
        $body = [];
        $inside = false;

        foreach ($lines as $line) {
            if (str_starts_with($line, '-----BEGIN')) {
                $inside = true;

                continue;
            }
            if ($inside && str_starts_with($line, '-----END')) {
                break;
            }
            if ($inside) {
                $body[] = $line;
            }
        }

        return base64_decode(implode('', $body), true);
    }

    private function signerCertificateSerialHex(): string
    {
        $parsed = openssl_x509_parse($this->signerCertificate());

        return strtoupper((string) ($parsed['serialNumberHex'] ?? ''));
    }
}

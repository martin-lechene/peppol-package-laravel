<?php

namespace PeppolPackage\EInvoices\Tests;

use Orchestra\Testbench\TestCase as OrchestraTestCase;
use PeppolPackage\EInvoices\InvoiceServiceProvider;
use PeppolPackage\EInvoices\Models\Invoice;
use PeppolPackage\EInvoices\Models\InvoiceLineItem;
use RobRichards\XMLSecLibs\XMLSecurityDSig;
use RobRichards\XMLSecLibs\XMLSecurityKey;

abstract class TestCase extends OrchestraTestCase
{
    protected function getPackageProviders($app): array
    {
        return [InvoiceServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('database.default', 'testbench');
        $app['config']->set('database.connections.testbench', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);
    }

    protected function defineDatabaseMigrations(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
    }

    protected function xsdPath(string $file = 'UBL-Invoice-2.1.xsd'): string
    {
        return realpath(__DIR__.'/../resources/schemas/ubl2.1/maindoc/'.$file);
    }

    protected function makeInvoice(array $overrides = [], array $lines = []): Invoice
    {
        $defaults = [
            'organization_id' => 1,
            'invoice_number' => 'INV-2026-001',
            'issue_date' => '2026-01-15',
            'due_date' => '2026-02-15',
            'currency' => 'EUR',
            'type' => 'ORIGINAL',
            'format' => 'PEPPOL_BIS',
            'status' => 'DRAFT',
            'sender_siret' => 'BE0123456789',
            'recipient_siret' => 'BE0987654321',
            'amount_subtotal' => 1210.00,
            'amount_tax' => 190.00,
            'amount_total' => 1400.00,
        ];

        $invoice = Invoice::create(array_merge($defaults, $overrides));

        $defaultLines = [
            ['description' => 'Consulting', 'quantity' => 5, 'unit' => 'HOUR', 'unit_price' => 100.00, 'tax_category' => 'S', 'tax_percent' => 21],
        ];
        foreach (array_merge($defaultLines, $lines) as $lineData) {
            $lineData['invoice_id'] = $invoice->id;
            InvoiceLineItem::create($lineData);
        }

        return $invoice->load('lineItems');
    }

    protected function signerCertificate(): string
    {
        return (string) file_get_contents(__DIR__.'/fixtures/certs/signer.crt');
    }

    protected function signerPrivateKey(): string
    {
        return (string) file_get_contents(__DIR__.'/fixtures/certs/signer.key');
    }

    protected function signerPublicKeyPem(): string
    {
        $cert = openssl_x509_read($this->signerCertificate());
        $public = openssl_pkey_get_public($cert);
        $details = openssl_pkey_get_details($public);

        return (string) ($details['key'] ?? '');
    }

    /**
     * @return array<int|string, \DOMNode>
     */
    protected function verifyXadesSignature(string $xml): array
    {
        $document = new \DOMDocument;
        $document->loadXML($xml);

        $key = new XMLSecurityKey(
            XMLSecurityKey::RSA_SHA256,
            ['type' => 'public']
        );
        $key->loadKey($this->signerPublicKeyPem(), false);

        $dsig = new XMLSecurityDSig;
        $dsig->idKeys = ['wsu:Id'];
        $dsig->idNS = ['wsu' => 'http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-wssecurity-utility-1.0.xsd'];

        return $dsig->verifyDocument($key, $document);
    }
}

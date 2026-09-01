<?php

namespace PeppolPackage\EInvoices;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use PeppolPackage\EInvoices\Models\Invoice;
use PeppolPackage\EInvoices\Signing\XadesSigner;
use PeppolPackage\EInvoices\Support\PeppolBisXmlBuilder;
use PeppolPackage\EInvoices\Support\TransmissionResult;
use PeppolPackage\EInvoices\Validation\En16931Validator;
use PeppolPackage\EInvoices\Validation\ValidationResult;

class InvoiceManager
{
    public function __construct(
        protected array $config
    ) {}

    public function generate(Invoice $invoice, string $format = 'PEPPOL_BIS'): string
    {
        return match ($format) {
            'PEPPOL_BIS' => PeppolBisXmlBuilder::build($invoice),
            default => PeppolBisXmlBuilder::build($invoice),
        };
    }

    public function validate(string $xml): ValidationResult
    {
        $schema = $this->config['validation']['schema'] ?? null;

        return (new En16931Validator($schema ?: null))->validate($xml);
    }

    public function sign(string $xml, ?string $certificate = null, ?string $privateKey = null, ?string $keyPassword = null): string
    {
        $certificate ??= $this->config['signature']['certificate'] ?? null;
        $privateKey ??= $this->config['signature']['private_key'] ?? null;
        $keyPassword ??= $this->config['signature']['key_password'] ?? null;

        if ($certificate === null || $privateKey === null) {
            throw new \InvalidArgumentException('Signing requires a certificate and private key.');
        }

        return (new XadesSigner($certificate, $privateKey, $keyPassword))->sign($xml);
    }

    public function transmit(Invoice $invoice): TransmissionResult
    {
        $mode = $this->config['transmission']['mode'] ?? 'stub';

        if ($mode === 'http' && ! empty($this->config['transmission']['endpoint'])) {
            return $this->transmitViaHttp($invoice);
        }

        return new TransmissionResult(true);
    }

    private function transmitViaHttp(Invoice $invoice): TransmissionResult
    {
        $endpoint = (string) $this->config['transmission']['endpoint'];
        $apiKey = (string) ($this->config['transmission']['api_key'] ?? '');
        $xml = (string) ($invoice->xml_content ?? '');

        if ($xml === '') {
            return new TransmissionResult(false, 'Invoice has no xml_content to transmit.');
        }

        $headers = [
            'Content-Type' => 'application/xml',
            'Accept' => 'application/json, application/xml, text/plain, */*',
        ];
        if ($apiKey !== '') {
            $headers['Authorization'] = 'Bearer '.$apiKey;
        }

        try {
            $client = $this->httpClient();
            $response = $client->post($endpoint, [
                'headers' => $headers,
                'body' => $xml,
            ]);
            $code = $response->getStatusCode();
            $ok = $code >= 200 && $code < 300;
            $body = $response->getBody()->getContents();

            return new TransmissionResult($ok, $ok ? null : 'HTTP '.$code.': '.substr($body, 0, 500));
        } catch (GuzzleException $e) {
            return new TransmissionResult(false, $e->getMessage());
        }
    }

    protected function httpClient(): Client
    {
        return new Client(['timeout' => 60, 'http_errors' => false]);
    }
}

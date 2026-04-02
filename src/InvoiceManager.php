<?php

namespace PeppolPackage\EInvoices;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use PeppolPackage\EInvoices\Models\Invoice;
use PeppolPackage\EInvoices\Support\PeppolBisXmlBuilder;
use PeppolPackage\EInvoices\Support\TransmissionResult;

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
            $client = new Client(['timeout' => 60, 'http_errors' => false]);
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
}

<?php

namespace PeppolPackage\EInvoices\Tests\Feature;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Promise\Create;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use PeppolPackage\EInvoices\InvoiceManager;
use PeppolPackage\EInvoices\Models\Invoice;
use PeppolPackage\EInvoices\Tests\TestCase;
use Psr\Http\Message\RequestInterface;

final class InvoiceManagerTransmissionTest extends TestCase
{
    public function test_http_transmission_post_sends_xml_with_auth_header(): void
    {
        $requests = [];

        $manager = $this->httpManager([
            new Response(202, [], 'accepted'),
        ], $requests);

        $invoice = new Invoice(['xml_content' => '<Invoice/>']);

        $result = $manager->transmit($invoice);

        $this->assertTrue($result->success);
        $this->assertNull($result->message);
        $this->assertCount(1, $requests);
        $this->assertSame('http://access-point.test/eb', (string) $requests[0]->getUri());
        $this->assertSame('application/xml', $requests[0]->getHeaderLine('Content-Type'));
        $this->assertSame('Bearer topsecret', $requests[0]->getHeaderLine('Authorization'));
        $this->assertSame('<Invoice/>', (string) $requests[0]->getBody());
    }

    public function test_http_transmission_reports_server_error(): void
    {
        $requests = [];

        $manager = $this->httpManager([
            new Response(500, [], 'internal error'),
        ], $requests);

        $invoice = new Invoice(['xml_content' => '<Invoice/>']);

        $result = $manager->transmit($invoice);

        $this->assertFalse($result->success);
        $this->assertStringContainsString('HTTP 500', (string) $result->message);
        $this->assertCount(1, $requests);
    }

    public function test_http_transmission_fails_without_xml_content(): void
    {
        $requests = [];

        $manager = $this->httpManager([], $requests);

        $result = $manager->transmit(new Invoice(['xml_content' => null]));

        $this->assertFalse($result->success);
        $this->assertStringContainsString('no xml_content', (string) $result->message);
        $this->assertCount(0, $requests);
    }

    public function test_http_transmission_fails_on_transport_exception(): void
    {
        $requests = [];

        $manager = $this->httpManager([
            new ConnectException('Connection refused', new Request('POST', 'http://access-point.test/eb')),
        ], $requests);

        $invoice = new Invoice(['xml_content' => '<Invoice/>']);

        $result = $manager->transmit($invoice);

        $this->assertFalse($result->success);
        $this->assertStringContainsString('Connection refused', (string) $result->message);
    }

    /**
     * @param  list<Response|\Throwable>  $responses
     * @param  list<RequestInterface>  $requests
     */
    private function httpManager(array $responses, array &$requests): InvoiceManager
    {
        $handler = function (RequestInterface $request, array $options) use (&$responses, &$requests) {
            $requests[] = $request;

            $next = array_shift($responses);
            if ($next instanceof \Throwable) {
                throw $next;
            }

            return Create::promiseFor($next);
        };

        $client = new Client(['handler' => $handler, 'http_errors' => false]);

        return new class($client) extends InvoiceManager
        {
            public function __construct(private Client $client)
            {
                parent::__construct([
                    'transmission' => [
                        'mode' => 'http',
                        'endpoint' => 'http://access-point.test/eb',
                        'api_key' => 'topsecret',
                    ],
                ]);
            }

            protected function httpClient(): Client
            {
                return $this->client;
            }
        };
    }
}

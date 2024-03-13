<?php

namespace Rafik\PscWorldWebservice\Test;

use Nyholm\Psr7\Factory\Psr17Factory;
use Psr\Http\Message\StreamFactoryInterface;
use Rafik\PscWorldWebservice\PscWorldClient;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Psr18Client;
use Symfony\Component\HttpClient\Response\MockResponse;

class PscWorldClientTest extends TestCase
{
    private PscWorldClient $client;

    private StreamFactoryInterface $streamFactory;

    protected function setUp(): void
    {
        $httpClient = new MockHttpClient(function ($method, $url, $options) {
            $this->assertSoapRequest($method, $url, $options);

            $action = $this->parseHeader($options['normalized_headers']['soapaction'][0]);
            $payload = $options['body'];
            if ($action === 'http://tempuri.org/IClient/Genera') {
                $this->assertSoapPayload('Generate', $payload);

                return new MockResponse($this->loadSoapResponse('Generate'));
            }
            if ($action === 'http://tempuri.org/IClient/Recupera') {
                $this->assertSoapPayload('Recover', $payload);

                return new MockResponse($this->loadSoapResponse('Recover'));
            }
            if ($action === 'http://tempuri.org/IClient/ValidaConstancia') {
                $this->assertSoapPayload('InvalidCertificate', $payload);

                return new MockResponse($this->loadSoapResponse('InvalidCertificate'));
            }

            throw new \RuntimeException('Should not be thrown');
        });
        $psr17Factory = new Psr17Factory();
        $psr18Client = new Psr18Client($httpClient, $psr17Factory, $psr17Factory);

        $this->client = new PscWorldClient($psr18Client, $psr17Factory, $psr17Factory, 'test', 'test');
        $this->streamFactory = new Psr17Factory();
    }

    public function testGenerate(): void
    {
        $data = $this->streamFactory->createStream('This is a test data');

        $certificate = $this->client->generate('1234', $data);
        $this->assertEquals('Super secret certificate', $certificate);
    }

    public function testRecover(): void
    {
        $certificate = $this->client->recover('1234');
        $this->assertEquals('Super secret certificate', $certificate);
    }

    public function testInvalidCertificate(): void
    {
        $certificate = $this->client->validate('Invalid Certificate');

        $this->assertFalse($certificate->isValid());
        $this->assertNull($certificate->getIssuer());
        $this->assertNull($certificate->getRoot());
    }

    public function testValidCertificate(): void
    {
        $certificate = $this->client->validate('Valid Certificate');

        $this->assertTrue($certificate->isValid());
    }

	public function testValidateData(): void
	{
		// This is a test certificate from PSC World
		$certificate = base64_encode($this->loadResource('certificate.ber'));
		$data = $this->streamFactory->createStream('1234');

		$this->assertTrue($this->client->validateData($certificate, $data));
	}

    private function assertSoapRequest(string $method, string $url, array $options): void
    {
        $this->assertEquals('POST', $method);
        $this->assertEquals('https://nomtsclient.pscworld.com/NOMTS_Client.svc', $url);

        $this->assertArrayHasKey('content-type', $options['normalized_headers']);
        $contentType = $options['normalized_headers']['content-type'];
        $this->assertCount(1, $contentType);
        $this->assertEquals('Content-Type: text/xml; charset=utf-8', $contentType[0]);

        $this->assertArrayHasKey('soapaction', $options['normalized_headers']);
        $soapAction = $options['normalized_headers']['soapaction'];
        $this->assertCount(1, $soapAction);
    }

    private function assertSoapPayload(string $expectedAction, string $actual): void
    {
        $this->assertXmlStringEqualsXmlFile($this->getResourceDir() . DIRECTORY_SEPARATOR . $expectedAction . 'Request.xml', $actual);
    }

    private function loadSoapResponse(string $action): string
    {
        return $this->loadResource($action . 'Response.xml');
    }

    private function parseHeader(string $header): string
    {
        $tokenPos = strpos($header, ':');
        $parsedHeader = substr($header, $tokenPos + 1);

        return trim($parsedHeader);
    }
}
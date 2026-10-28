<?php

declare(strict_types=1);

namespace Omnipay\PayPalV2\Tests\Endpoints;

use Omnipay\Common\GatewayFactory;
use Omnipay\Common\GatewayInterface;
use Omnipay\Tests\TestCase;
use Psr\Http\Message\RequestInterface;

/**
 * The gateway as a consuming app holds it: made by Omnipay's factory by name, initialised with the
 * app's REST credentials in test mode and no token, and its HTTP client the mock client, which
 * answers with the fixtures in Mock/ and records every request sent. Every operation first fetches
 * the OAuth token, so an operation's fixtures start with TokenSuccess.txt.
 */
abstract class EndpointTestCase extends TestCase
{
    protected const string CLIENT_ID = 'sandbox_client_id';

    protected const string CLIENT_SECRET = 'sandbox_client_secret';

    /** The access_token in Mock/TokenSuccess.txt. */
    protected const string ACCESS_TOKEN = 'A21AA_sandbox_access_token';

    protected const string TOKEN_URL = 'https://api-m.sandbox.paypal.com/v1/oauth2/token';

    protected GatewayInterface $gateway;

    protected function setUp(): void
    {
        parent::setUp();

        $this->gateway = (new GatewayFactory)->create('PayPalV2', $this->getHttpClient(), $this->getHttpRequest());
        $this->gateway->initialize([
            'clientId' => self::CLIENT_ID,
            'clientSecret' => self::CLIENT_SECRET,
            'testMode' => true,
        ]);
    }

    /** The operation's request, sent after the token request; fails unless exactly those two were sent. */
    protected function sent(): RequestInterface
    {
        $requests = $this->getMockClient()->getRequests();
        $this->assertCount(2, $requests, 'The operation sent '.count($requests).' requests, expected the token request then its own.');
        $this->assertTokenRequest($requests[0]);

        return $requests[1];
    }

    /** A refused operation sent nothing but the token request its creation fetched. */
    protected function assertOnlyTokenSent(): void
    {
        $requests = $this->getMockClient()->getRequests();
        $this->assertCount(1, $requests, 'A refused operation reached PayPal.');
        $this->assertTokenRequest($requests[0]);
    }

    protected function assertTokenRequest(RequestInterface $request): void
    {
        $this->assertSame('POST', $request->getMethod());
        $this->assertSame(self::TOKEN_URL, (string) $request->getUri());
        $this->assertSame('Basic '.base64_encode(self::CLIENT_ID.':'.self::CLIENT_SECRET), $request->getHeaderLine('Authorization'));
        $this->assertSame('application/x-www-form-urlencoded', $request->getHeaderLine('Content-Type'));
        $this->assertSame('grant_type=client_credentials', (string) $request->getBody());
    }

    protected function assertBearer(RequestInterface $request): void
    {
        $this->assertSame('Bearer '.self::ACCESS_TOKEN, $request->getHeaderLine('Authorization'));
    }

    /** @return array<array-key, mixed> */
    protected function body(RequestInterface $request): array
    {
        return json_decode((string) $request->getBody(), true, 512, JSON_THROW_ON_ERROR);
    }
}

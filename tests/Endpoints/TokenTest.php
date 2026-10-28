<?php

declare(strict_types=1);

namespace Omnipay\PayPalV2\Tests\Endpoints;

use PHPUnit\Framework\Attributes\Test;

/**
 * The OAuth token every PayPal call carries: fetched with the app's credentials, and kept by the
 * gateway until it expires, so later calls reuse it.
 */
final class TokenTest extends EndpointTestCase
{
    #[Test]
    #[Calls('Gateway::createToken')]
    public function create_token_exchanges_the_credentials_for_an_access_token(): void
    {
        $this->setMockHttpResponse('TokenSuccess.txt');

        $response = $this->gateway->createToken()->send();

        $requests = $this->getMockClient()->getRequests();
        $this->assertCount(1, $requests);
        $this->assertTokenRequest($requests[0]);
        $this->assertTrue($response->isSuccessful());
        $this->assertSame(self::ACCESS_TOKEN, $response->getToken());
        $this->assertSame(32400, $response->getExpires());
    }

    #[Test]
    public function create_token_reports_credentials_paypal_refuses(): void
    {
        $this->setMockHttpResponse('TokenRefused.txt');

        $response = $this->gateway->createToken()->send();

        $this->assertCount(1, $this->getMockClient()->getRequests());
        $this->assertFalse($response->isSuccessful());
        $this->assertNull($response->getToken());
        $this->assertSame('Client Authentication failed', $response->getMessage());
        $this->assertSame('invalid_client', $response->getCode());
    }

    #[Test]
    #[Calls('Gateway::getToken')]
    public function get_token_fetches_the_token_once_and_keeps_it_until_it_expires(): void
    {
        $this->setMockHttpResponse('TokenSuccess.txt');
        $before = time();

        $this->assertSame(self::ACCESS_TOKEN, $this->gateway->getToken());
        $this->assertSame(self::ACCESS_TOKEN, $this->gateway->getToken());

        $requests = $this->getMockClient()->getRequests();
        $this->assertCount(1, $requests, 'A kept token was fetched again.');
        $this->assertTokenRequest($requests[0]);
        $this->assertTrue($this->gateway->hasToken());
        $this->assertGreaterThanOrEqual($before + 32400, $this->gateway->getTokenExpires());
    }
}

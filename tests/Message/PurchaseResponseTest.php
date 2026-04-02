<?php

namespace Omnipay\PayPalV2\Tests\Message;

use Omnipay\PayPalV2\Message\PurchaseResponse;
use PHPUnit\Framework\TestCase;

class PurchaseResponseTest extends TestCase
{
    public function testSuccessfulPurchaseResponse(): void
    {
        $data = [
            'id' => '5O190127TN364715T',
            'status' => 'PAYER_ACTION_REQUIRED',
            'links' => [
                ['href' => 'https://api.sandbox.paypal.com/v2/checkout/orders/5O190127TN364715T', 'rel' => 'self', 'method' => 'GET'],
                ['href' => 'https://www.sandbox.paypal.com/checkoutnow?token=5O190127TN364715T', 'rel' => 'payer-action', 'method' => 'GET'],
            ],
            'purchase_units' => [
                ['reference_id' => 'TXN_001'],
            ],
        ];

        $response = new PurchaseResponse($this->createMock(\Omnipay\Common\Message\RequestInterface::class), $data);

        $this->assertFalse($response->isSuccessful());
        $this->assertTrue($response->isRedirect());
        $this->assertSame('https://www.sandbox.paypal.com/checkoutnow?token=5O190127TN364715T', $response->getRedirectUrl());
        $this->assertSame('GET', $response->getRedirectMethod());
        $this->assertNull($response->getRedirectData());
        $this->assertSame('5O190127TN364715T', $response->getTransactionReference());
        $this->assertSame('TXN_001', $response->getTransactionId());
        $this->assertSame('PAYER_ACTION_REQUIRED', $response->getOrderStatus());
    }

    public function testNoRedirectWhenLinksAreMissing(): void
    {
        $data = ['id' => '5O190127TN364715T', 'status' => 'CREATED'];

        $response = new PurchaseResponse($this->createMock(\Omnipay\Common\Message\RequestInterface::class), $data);

        $this->assertFalse($response->isSuccessful());
        $this->assertFalse($response->isRedirect());
        $this->assertNull($response->getRedirectUrl());
    }

    public function testNoRedirectWhenPayerActionLinkMissing(): void
    {
        $data = [
            'id' => '5O190127TN364715T',
            'links' => [
                ['href' => 'https://api.sandbox.paypal.com/v2/checkout/orders/5O190127TN364715T', 'rel' => 'self', 'method' => 'GET'],
            ],
        ];

        $response = new PurchaseResponse($this->createMock(\Omnipay\Common\Message\RequestInterface::class), $data);

        $this->assertFalse($response->isRedirect());
        $this->assertNull($response->getRedirectUrl());
    }

    public function testErrorResponse(): void
    {
        $data = [
            'name' => 'UNPROCESSABLE_ENTITY',
            'message' => 'The requested action could not be performed.',
            'details' => [
                ['field' => '/purchase_units/amount/value', 'issue' => 'CURRENCY_NOT_SUPPORTED', 'description' => 'Currency code is not supported.'],
            ],
        ];

        $response = new PurchaseResponse($this->createMock(\Omnipay\Common\Message\RequestInterface::class), $data);

        $this->assertFalse($response->isSuccessful());
        $this->assertFalse($response->isRedirect());
        $this->assertSame('The requested action could not be performed.', $response->getMessage());
        $this->assertSame('UNPROCESSABLE_ENTITY', $response->getCode());
        $this->assertNull($response->getTransactionReference());
        $this->assertNull($response->getTransactionId());
    }

    public function testErrorResponseWithDetailsOnly(): void
    {
        $data = [
            'name' => 'INVALID_REQUEST',
            'details' => [
                ['field' => '/amount', 'description' => 'Amount is required'],
                ['field' => '/currency', 'issue' => 'MISSING_FIELD'],
            ],
        ];

        $response = new PurchaseResponse($this->createMock(\Omnipay\Common\Message\RequestInterface::class), $data);

        $this->assertStringContainsString('Amount is required', $response->getMessage());
        $this->assertStringContainsString('MISSING_FIELD', $response->getMessage());
    }

    public function testEmptyResponse(): void
    {
        $response = new PurchaseResponse($this->createMock(\Omnipay\Common\Message\RequestInterface::class), []);

        $this->assertFalse($response->isSuccessful());
        $this->assertFalse($response->isRedirect());
        $this->assertNull($response->getTransactionReference());
        $this->assertNull($response->getTransactionId());
        $this->assertNull($response->getOrderStatus());
        $this->assertNull($response->getMessage());
    }

    public function testMessageFallbackToStatus(): void
    {
        $data = ['status' => 'CREATED'];

        $response = new PurchaseResponse($this->createMock(\Omnipay\Common\Message\RequestInterface::class), $data);

        $this->assertSame('Order status: CREATED', $response->getMessage());
    }
}

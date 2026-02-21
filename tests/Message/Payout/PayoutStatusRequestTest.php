<?php

namespace Omnipay\PayPalV2\Tests\Message\Payout;

use Omnipay\PayPalV2\Message\Payout\PayoutStatusRequest;
use Omnipay\PayPalV2\Message\Payout\PayoutStatusResponse;
use Omnipay\Tests\TestCase;

class PayoutStatusRequestTest extends TestCase
{
    private PayoutStatusRequest $request;

    public function setUp(): void
    {
        parent::setUp();
        $this->request = new PayoutStatusRequest($this->getHttpClient(), $this->getHttpRequest());
        $this->request->initialize([
            'clientId' => 'test_client_id',
            'clientSecret' => 'test_client_secret',
            'token' => 'test_bearer_token',
            'testMode' => true,
            'payoutItemId' => 'ITEM_PAY_001',
        ]);
    }

    public function testValidationRequiresPayoutItemId(): void
    {
        $this->request->setPayoutItemId(null);
        $this->expectException(\Omnipay\Common\Exception\InvalidRequestException::class);
        $this->request->getData();
    }

    public function testEndpointIncludesPayoutItemId(): void
    {
        $this->setMockHttpResponse('PayoutItemSuccess.txt');
        $this->request->send();

        $lastRequest = $this->getMockClient()->getLastRequest();
        $this->assertStringContainsString(
            '/v1/payments/payouts-item/ITEM_PAY_001',
            (string) $lastRequest->getUri()
        );
    }

    public function testUsesGetMethod(): void
    {
        $this->setMockHttpResponse('PayoutItemSuccess.txt');
        $this->request->send();

        $lastRequest = $this->getMockClient()->getLastRequest();
        $this->assertSame('GET', $lastRequest->getMethod());
    }

    public function testSuccessResponse(): void
    {
        $this->setMockHttpResponse('PayoutItemSuccess.txt');
        $response = $this->request->send();

        $this->assertInstanceOf(PayoutStatusResponse::class, $response);
        $this->assertTrue($response->isSuccessful());
        $this->assertFalse($response->isPending());
        $this->assertFalse($response->isFailed());
        $this->assertSame('SUCCESS', $response->getTransactionStatus());
        $this->assertSame('ITEM_PAY_001', $response->getTransactionReference());
        $this->assertSame('TXN_PAYPAL_001', $response->getPayPalTransactionId());
        $this->assertSame('BATCH_PAY_001', $response->getPayoutBatchId());
        $this->assertSame('TXN_PAY_202602_001', $response->getSenderItemId());
    }

    public function testPendingResponse(): void
    {
        $this->setMockHttpResponse('PayoutItemPending.txt');
        $response = $this->request->send();

        $this->assertFalse($response->isSuccessful());
        $this->assertTrue($response->isPending());
        $this->assertSame('PENDING', $response->getTransactionStatus());
    }

    public function testFailedResponse(): void
    {
        $this->setMockHttpResponse('PayoutItemFailed.txt');
        $response = $this->request->send();

        $this->assertFalse($response->isSuccessful());
        $this->assertTrue($response->isFailed());
        $this->assertSame('FAILED', $response->getTransactionStatus());
        $this->assertSame('RECEIVER_UNREGISTERED', $response->getCode());
        $this->assertSame('Receiver is unregistered', $response->getMessage());
    }
}

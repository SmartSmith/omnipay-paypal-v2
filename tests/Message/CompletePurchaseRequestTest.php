<?php

namespace Omnipay\PayPalV2\Tests\Message;

use Omnipay\Common\Exception\InvalidRequestException;
use Omnipay\PayPalV2\Message\CompletePurchaseRequest;
use Omnipay\Tests\TestCase;

class CompletePurchaseRequestTest extends TestCase
{
    private CompletePurchaseRequest $request;

    public function setUp(): void
    {
        parent::setUp();
        $this->request = new CompletePurchaseRequest($this->getHttpClient(), $this->getHttpRequest());
        $this->request->initialize([
            'clientId' => 'test_client_id',
            'clientSecret' => 'test_client_secret',
            'token' => 'test_bearer_token',
            'testMode' => true,
        ]);
    }

    public function testGetDataWithTransactionReference(): void
    {
        $this->request->setTransactionReference('ORDER_123');
        $data = $this->request->getData();

        $this->assertSame('ORDER_123', $data['order_id']);
    }

    public function testGetDataWithTokenParameter(): void
    {
        // PayPal passes the order ID as 'token' query parameter on callback redirect
        $this->request->setToken('ORDER_FROM_REDIRECT');
        $data = $this->request->getData();

        $this->assertSame('ORDER_FROM_REDIRECT', $data['order_id']);
    }

    public function testTransactionReferenceTakesPriorityOverToken(): void
    {
        $this->request->setTransactionReference('EXPLICIT_ORDER');
        $this->request->setToken('TOKEN_ORDER');
        $data = $this->request->getData();

        $this->assertSame('EXPLICIT_ORDER', $data['order_id']);
    }

    public function testMissingOrderIdThrowsException(): void
    {
        // Clear token so neither transactionReference nor token is available
        $this->request->setToken('');
        $this->expectException(InvalidRequestException::class);
        $this->expectExceptionMessage('PayPal order ID is required');
        $this->request->getData();
    }

    public function testSandboxEndpoint(): void
    {
        $this->request->setTransactionReference('ORDER_123');
        $this->setMockHttpResponse('CaptureSuccess.txt');
        $response = $this->request->send();

        $lastRequest = $this->getMockClient()->getLastRequest();
        $uri = (string) $lastRequest->getUri();
        $this->assertStringContainsString('sandbox.paypal.com', $uri);
        $this->assertStringContainsString('/v2/checkout/orders/ORDER_123/capture', $uri);
    }

    public function testProductionEndpoint(): void
    {
        $this->request->setTestMode(false);
        $this->request->setTransactionReference('ORDER_123');
        $this->setMockHttpResponse('CaptureSuccess.txt');
        $response = $this->request->send();

        $lastRequest = $this->getMockClient()->getLastRequest();
        $uri = (string) $lastRequest->getUri();
        $this->assertStringContainsString('api-m.paypal.com', $uri);
        $this->assertStringNotContainsString('sandbox', $uri);
    }

    public function testAuthHeadersIncluded(): void
    {
        $this->request->setTransactionReference('ORDER_123');
        $this->setMockHttpResponse('CaptureSuccess.txt');
        $this->request->send();

        $lastRequest = $this->getMockClient()->getLastRequest();
        $this->assertSame('Bearer test_bearer_token', $lastRequest->getHeaderLine('Authorization'));
        $this->assertSame('application/json', $lastRequest->getHeaderLine('Content-Type'));
    }

    public function testRequestMethodIsPost(): void
    {
        $this->request->setTransactionReference('ORDER_123');
        $this->setMockHttpResponse('CaptureSuccess.txt');
        $this->request->send();

        $lastRequest = $this->getMockClient()->getLastRequest();
        $this->assertSame('POST', $lastRequest->getMethod());
    }

    public function testRequestBodyIsEmptyJson(): void
    {
        $this->request->setTransactionReference('ORDER_123');
        $this->setMockHttpResponse('CaptureSuccess.txt');
        $this->request->send();

        $lastRequest = $this->getMockClient()->getLastRequest();
        $this->assertSame('{}', (string) $lastRequest->getBody());
    }

    public function testOrderIdIsUrlEncoded(): void
    {
        $this->request->setTransactionReference('ORDER WITH SPACES');
        $this->setMockHttpResponse('CaptureSuccess.txt');
        $this->request->send();

        $lastRequest = $this->getMockClient()->getLastRequest();
        $uri = (string) $lastRequest->getUri();
        $this->assertStringContainsString('ORDER+WITH+SPACES', $uri);
        $this->assertStringNotContainsString('ORDER WITH SPACES', $uri);
    }

    public function testSuccessfulCaptureResponse(): void
    {
        $this->request->setTransactionReference('5O190127TN364715T');
        $this->setMockHttpResponse('CaptureSuccess.txt');
        $response = $this->request->send();

        $this->assertTrue($response->isSuccessful());
        $this->assertFalse($response->isPending());
        $this->assertSame('CAP_87654321', $response->getTransactionReference());
        $this->assertSame('TXN_001', $response->getTransactionId());
        $this->assertSame('COMPLETED', $response->getOrderStatus());
        $this->assertSame('COMPLETED', $response->getCaptureStatus());
    }

    public function testApprovedButNotCapturedResponse(): void
    {
        $this->request->setTransactionReference('5O190127TN364715T');
        $this->setMockHttpResponse('CaptureApproved.txt');
        $response = $this->request->send();

        $this->assertFalse($response->isSuccessful());
        $this->assertTrue($response->isPending());
        $this->assertSame('APPROVED', $response->getOrderStatus());
    }

    public function testCaptureErrorResponse(): void
    {
        $this->request->setTransactionReference('5O190127TN364715T');
        $this->setMockHttpResponse('CaptureError.txt');
        $response = $this->request->send();

        $this->assertFalse($response->isSuccessful());
        $this->assertFalse($response->isPending());
        $this->assertSame('UNPROCESSABLE_ENTITY', $response->getCode());
        // getMessage() returns 'message' field first, then details
        $this->assertSame('The requested action could not be performed.', $response->getMessage());
    }
}

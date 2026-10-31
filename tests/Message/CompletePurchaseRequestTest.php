<?php

namespace Omnipay\PayPalV2\Tests\Message;

use Omnipay\Common\Exception\InvalidRequestException;
use Omnipay\PayPalV2\Message\CompletePurchaseRequest;
use Omnipay\Tests\TestCase;

class CompletePurchaseRequestTest extends TestCase
{
    private CompletePurchaseRequest $request;

    protected function setUp(): void
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

    public function test_get_data_with_transaction_reference(): void
    {
        $this->request->setTransactionReference('ORDER_123');
        $data = $this->request->getData();

        $this->assertSame('ORDER_123', $data['order_id']);
    }

    public function test_get_data_takes_the_return_redirect_order_id_as_transaction_reference(): void
    {
        // PayPal returns the buyer with ?token=<order id>; Gateway::completePurchase() hands that
        // order id to the request as its transactionReference, never as the OAuth token.
        $this->request->setTransactionReference('ORDER_FROM_REDIRECT');
        $data = $this->request->getData();

        $this->assertSame('ORDER_FROM_REDIRECT', $data['order_id']);
    }

    public function test_order_id_comes_from_transaction_reference_not_the_oauth_token(): void
    {
        $this->request->setTransactionReference('EXPLICIT_ORDER');
        $this->request->setToken('OAUTH_TOKEN');
        $data = $this->request->getData();

        $this->assertSame('EXPLICIT_ORDER', $data['order_id']);
    }

    public function test_missing_transaction_reference_throws_even_with_the_oauth_token_set(): void
    {
        // setUp() sets the gateway's OAuth token; it is never an order id.
        $this->expectException(InvalidRequestException::class);
        $this->expectExceptionMessage('PayPal order ID is required as transactionReference');
        $this->request->getData();
    }

    public function test_complete_purchase_without_transaction_reference_sends_no_request(): void
    {
        $this->setMockHttpResponse('CaptureSuccess.txt');

        try {
            $this->request->send();
            $this->fail('completePurchase() without a transactionReference must throw');
        } catch (InvalidRequestException $e) {
            $this->assertStringContainsString('transactionReference', $e->getMessage());
        }

        $this->assertCount(0, $this->getMockClient()->getRequests());
    }

    public function test_sandbox_endpoint(): void
    {
        $this->request->setTransactionReference('ORDER_123');
        $this->setMockHttpResponse('CaptureSuccess.txt');
        $response = $this->request->send();

        $lastRequest = $this->getMockClient()->getLastRequest();
        $uri = (string) $lastRequest->getUri();
        $this->assertStringContainsString('sandbox.paypal.com', $uri);
        $this->assertStringContainsString('/v2/checkout/orders/ORDER_123/capture', $uri);
    }

    public function test_production_endpoint(): void
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

    public function test_auth_headers_included(): void
    {
        $this->request->setTransactionReference('ORDER_123');
        $this->setMockHttpResponse('CaptureSuccess.txt');
        $this->request->send();

        $lastRequest = $this->getMockClient()->getLastRequest();
        $this->assertSame('Bearer test_bearer_token', $lastRequest->getHeaderLine('Authorization'));
        $this->assertSame('application/json', $lastRequest->getHeaderLine('Content-Type'));
    }

    public function test_request_method_is_post(): void
    {
        $this->request->setTransactionReference('ORDER_123');
        $this->setMockHttpResponse('CaptureSuccess.txt');
        $this->request->send();

        $lastRequest = $this->getMockClient()->getLastRequest();
        $this->assertSame('POST', $lastRequest->getMethod());
    }

    public function test_request_body_is_empty_json(): void
    {
        $this->request->setTransactionReference('ORDER_123');
        $this->setMockHttpResponse('CaptureSuccess.txt');
        $this->request->send();

        $lastRequest = $this->getMockClient()->getLastRequest();
        $this->assertSame('{}', (string) $lastRequest->getBody());
    }

    public function test_order_id_is_url_encoded(): void
    {
        $this->request->setTransactionReference('ORDER WITH SPACES');
        $this->setMockHttpResponse('CaptureSuccess.txt');
        $this->request->send();

        $lastRequest = $this->getMockClient()->getLastRequest();
        $uri = (string) $lastRequest->getUri();
        $this->assertStringContainsString('ORDER+WITH+SPACES', $uri);
        $this->assertStringNotContainsString('ORDER WITH SPACES', $uri);
    }

    public function test_successful_capture_response(): void
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

    public function test_approved_but_not_captured_response(): void
    {
        $this->request->setTransactionReference('5O190127TN364715T');
        $this->setMockHttpResponse('CaptureApproved.txt');
        $response = $this->request->send();

        $this->assertFalse($response->isSuccessful());
        $this->assertTrue($response->isPending());
        $this->assertSame('APPROVED', $response->getOrderStatus());
    }

    public function test_capture_error_response(): void
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

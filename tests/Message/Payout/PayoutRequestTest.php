<?php

namespace Omnipay\PayPalV2\Tests\Message\Payout;

use Omnipay\PayPalV2\Message\Payout\PayoutRequest;
use Omnipay\PayPalV2\Message\Payout\PayoutResponse;
use Omnipay\Tests\TestCase;

class PayoutRequestTest extends TestCase
{
    private PayoutRequest $request;

    public function setUp(): void
    {
        parent::setUp();
        $this->request = new PayoutRequest($this->getHttpClient(), $this->getHttpRequest());
        $this->request->initialize([
            'clientId' => 'test_client_id',
            'clientSecret' => 'test_client_secret',
            'token' => 'test_bearer_token',
            'testMode' => true,
            'amount' => '250.00',
            'currency' => 'USD',
            'recipientEmail' => 'mentor@example.com',
            'senderBatchId' => 'TXN_PAY_202602_001',
            'senderItemId' => 'ITEM_202602_001',
            'emailSubject' => 'You have a payment',
            'note' => 'Monthly payout for February 2026',
        ]);
    }

    public function testGetDataReturnsCorrectStructure(): void
    {
        $data = $this->request->getData();

        $this->assertSame('TXN_PAY_202602_001', $data['sender_batch_header']['sender_batch_id']);
        $this->assertSame('You have a payment', $data['sender_batch_header']['email_subject']);
        $this->assertCount(1, $data['items']);
        $this->assertSame('EMAIL', $data['items'][0]['recipient_type']);
        $this->assertSame('250.00', $data['items'][0]['amount']['value']);
        $this->assertSame('USD', $data['items'][0]['amount']['currency']);
        $this->assertSame('mentor@example.com', $data['items'][0]['receiver']);
        $this->assertSame('ITEM_202602_001', $data['items'][0]['sender_item_id']);
        $this->assertSame('Monthly payout for February 2026', $data['items'][0]['note']);
    }

    public function testOptionalFieldsOmittedWhenNotSet(): void
    {
        $this->request->setEmailSubject(null);
        $this->request->setEmailMessage(null);
        $this->request->setNote(null);

        $data = $this->request->getData();

        $this->assertArrayNotHasKey('email_subject', $data['sender_batch_header']);
        $this->assertArrayNotHasKey('email_message', $data['sender_batch_header']);
        $this->assertArrayNotHasKey('note', $data['items'][0]);
    }

    public function testValidationRequiresAmount(): void
    {
        $this->request->setAmount(null);
        $this->expectException(\Omnipay\Common\Exception\InvalidRequestException::class);
        $this->request->getData();
    }

    public function testValidationRequiresCurrency(): void
    {
        $this->request->setCurrency(null);
        $this->expectException(\Omnipay\Common\Exception\InvalidRequestException::class);
        $this->request->getData();
    }

    public function testValidationRequiresRecipientEmail(): void
    {
        $this->request->setRecipientEmail(null);
        $this->expectException(\Omnipay\Common\Exception\InvalidRequestException::class);
        $this->request->getData();
    }

    public function testValidationRequiresSenderBatchId(): void
    {
        $this->request->setSenderBatchId(null);
        $this->expectException(\Omnipay\Common\Exception\InvalidRequestException::class);
        $this->request->getData();
    }

    public function testValidationRequiresSenderItemId(): void
    {
        $this->request->setSenderItemId(null);
        $this->expectException(\Omnipay\Common\Exception\InvalidRequestException::class);
        $this->request->getData();
    }

    public function testEndpointIsPayouts(): void
    {
        $this->setMockHttpResponse('PayoutSuccess.txt');
        $this->request->send();

        $lastRequest = $this->getMockClient()->getLastRequest();
        $this->assertStringStartsWith(
            'https://api-m.sandbox.paypal.com/v1/payments/payouts',
            (string) $lastRequest->getUri()
        );
    }

    public function testUsesPostMethod(): void
    {
        $this->setMockHttpResponse('PayoutSuccess.txt');
        $this->request->send();

        $lastRequest = $this->getMockClient()->getLastRequest();
        $this->assertSame('POST', $lastRequest->getMethod());
    }

    public function testAuthHeadersIncluded(): void
    {
        $this->setMockHttpResponse('PayoutSuccess.txt');
        $this->request->send();

        $lastRequest = $this->getMockClient()->getLastRequest();
        $this->assertSame('Bearer test_bearer_token', $lastRequest->getHeaderLine('Authorization'));
        $this->assertSame('application/json', $lastRequest->getHeaderLine('Content-Type'));
    }

    public function testPendingResponse(): void
    {
        $this->setMockHttpResponse('PayoutSuccess.txt');
        $response = $this->request->send();

        $this->assertInstanceOf(PayoutResponse::class, $response);
        $this->assertFalse($response->isSuccessful());
        $this->assertTrue($response->isPending());
        $this->assertSame('BATCH_PAY_001', $response->getTransactionReference());
        $this->assertSame('TXN_PAY_202602_001', $response->getTransactionId());
        $this->assertSame('PENDING', $response->getBatchStatus());
    }

    public function testErrorResponse(): void
    {
        $this->setMockHttpResponse('PayoutError.txt');
        $response = $this->request->send();

        $this->assertFalse($response->isSuccessful());
        $this->assertFalse($response->isPending());
        $this->assertSame('VALIDATION_ERROR', $response->getCode());
    }
}

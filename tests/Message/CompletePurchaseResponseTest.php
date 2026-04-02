<?php

namespace Omnipay\PayPalV2\Tests\Message;

use Omnipay\PayPalV2\Message\CompletePurchaseResponse;
use PHPUnit\Framework\TestCase;

class CompletePurchaseResponseTest extends TestCase
{
    private function createResponse(array $data): CompletePurchaseResponse
    {
        return new CompletePurchaseResponse(
            $this->createMock(\Omnipay\Common\Message\RequestInterface::class),
            $data
        );
    }

    public function testCompletedStatusIsSuccessful(): void
    {
        $response = $this->createResponse([
            'id' => 'ORDER_123',
            'status' => 'COMPLETED',
            'purchase_units' => [
                [
                    'reference_id' => 'TXN_001',
                    'payments' => [
                        'captures' => [
                            ['id' => 'CAP_001', 'status' => 'COMPLETED', 'amount' => ['value' => '100.00', 'currency_code' => 'USD']],
                        ],
                    ],
                ],
            ],
        ]);

        $this->assertTrue($response->isSuccessful());
        $this->assertFalse($response->isPending());
        $this->assertSame('CAP_001', $response->getTransactionReference());
        $this->assertSame('TXN_001', $response->getTransactionId());
        $this->assertSame('COMPLETED', $response->getOrderStatus());
        $this->assertSame('COMPLETED', $response->getCaptureStatus());
        $this->assertSame('Payment captured successfully', $response->getMessage());
    }

    public function testApprovedStatusIsPending(): void
    {
        $response = $this->createResponse([
            'id' => 'ORDER_123',
            'status' => 'APPROVED',
            'purchase_units' => [['reference_id' => 'TXN_001', 'payments' => []]],
        ]);

        $this->assertFalse($response->isSuccessful());
        $this->assertTrue($response->isPending());
        $this->assertSame('APPROVED', $response->getOrderStatus());
        $this->assertSame('Payment approved, awaiting capture', $response->getMessage());
    }

    public function testPayerActionRequiredIsPending(): void
    {
        $response = $this->createResponse([
            'id' => 'ORDER_123',
            'status' => 'PAYER_ACTION_REQUIRED',
        ]);

        $this->assertFalse($response->isSuccessful());
        $this->assertTrue($response->isPending());
    }

    public function testVoidedStatusIsNotSuccessful(): void
    {
        $response = $this->createResponse([
            'id' => 'ORDER_123',
            'status' => 'VOIDED',
        ]);

        $this->assertFalse($response->isSuccessful());
        $this->assertFalse($response->isPending());
        $this->assertSame('Order voided', $response->getMessage());
    }

    public function testTransactionReferenceFallsBackToOrderId(): void
    {
        $response = $this->createResponse([
            'id' => 'ORDER_123',
            'status' => 'COMPLETED',
            'purchase_units' => [
                ['reference_id' => 'TXN_001', 'payments' => []],
            ],
        ]);

        // No captures → falls back to order id
        $this->assertSame('ORDER_123', $response->getTransactionReference());
    }

    public function testCaptureIdTakesPriorityOverOrderId(): void
    {
        $response = $this->createResponse([
            'id' => 'ORDER_123',
            'status' => 'COMPLETED',
            'purchase_units' => [
                [
                    'reference_id' => 'TXN_001',
                    'payments' => [
                        'captures' => [
                            ['id' => 'CAP_SPECIFIC', 'status' => 'COMPLETED'],
                        ],
                    ],
                ],
            ],
        ]);

        $this->assertSame('CAP_SPECIFIC', $response->getTransactionReference());
    }

    public function testErrorResponseWithMessage(): void
    {
        $response = $this->createResponse([
            'name' => 'UNPROCESSABLE_ENTITY',
            'message' => 'The requested action could not be performed.',
        ]);

        $this->assertFalse($response->isSuccessful());
        $this->assertSame('The requested action could not be performed.', $response->getMessage());
        $this->assertSame('UNPROCESSABLE_ENTITY', $response->getCode());
    }

    public function testErrorResponseWithDetails(): void
    {
        $response = $this->createResponse([
            'name' => 'INVALID_REQUEST',
            'details' => [
                ['issue' => 'MISSING_REQUIRED_PARAMETER', 'description' => 'A required parameter is missing'],
                ['issue' => 'INVALID_PARAMETER_VALUE', 'description' => 'Parameter value is invalid'],
            ],
        ]);

        $this->assertFalse($response->isSuccessful());
        $message = $response->getMessage();
        $this->assertStringContainsString('A required parameter is missing', $message);
        $this->assertStringContainsString('Parameter value is invalid', $message);
    }

    public function testEmptyResponse(): void
    {
        $response = $this->createResponse([]);

        $this->assertFalse($response->isSuccessful());
        $this->assertFalse($response->isPending());
        $this->assertNull($response->getTransactionReference());
        $this->assertNull($response->getTransactionId());
        $this->assertNull($response->getOrderStatus());
        $this->assertNull($response->getCaptureStatus());
        $this->assertSame('Order status: UNKNOWN', $response->getMessage());
    }

    public function testUnknownStatusMessage(): void
    {
        $response = $this->createResponse(['status' => 'SOME_NEW_STATUS']);

        $this->assertSame('Order status: SOME_NEW_STATUS', $response->getMessage());
    }

    public function testNullCode(): void
    {
        $response = $this->createResponse(['status' => 'COMPLETED']);

        $this->assertNull($response->getCode());
    }

    public function testMultipleCapturesReturnsFirst(): void
    {
        $response = $this->createResponse([
            'id' => 'ORDER_123',
            'status' => 'COMPLETED',
            'purchase_units' => [
                [
                    'payments' => [
                        'captures' => [
                            ['id' => 'CAP_FIRST', 'status' => 'COMPLETED'],
                            ['id' => 'CAP_SECOND', 'status' => 'COMPLETED'],
                        ],
                    ],
                ],
            ],
        ]);

        $this->assertSame('CAP_FIRST', $response->getTransactionReference());
        $this->assertSame('COMPLETED', $response->getCaptureStatus());
    }
}

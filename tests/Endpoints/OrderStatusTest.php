<?php

declare(strict_types=1);

namespace Omnipay\PayPalV2\Tests\Endpoints;

use Omnipay\Common\Exception\InvalidRequestException;
use PHPUnit\Framework\Attributes\Test;

/**
 * An app asks PayPal how an order stands with one GET of the order, which never captures it.
 */
final class OrderStatusTest extends EndpointTestCase
{
    private const string ORDER_ID = '5O190127TN364715T';

    private const string ORDER_URL = 'https://api-m.sandbox.paypal.com/v2/checkout/orders/'.self::ORDER_ID;

    #[Test]
    #[Calls('Gateway::fetchOrder')]
    public function fetch_order_reads_an_approved_order_with_one_get_and_captures_nothing(): void
    {
        $this->setMockHttpResponse(['TokenSuccess.txt', 'OrderApproved.txt']);

        $response = $this->gateway->fetchOrder(['transactionReference' => self::ORDER_ID])->send();

        $request = $this->sent();
        $this->assertSame('GET', $request->getMethod());
        $this->assertSame(self::ORDER_URL, (string) $request->getUri());
        $this->assertBearer($request);
        $this->assertSame('', (string) $request->getBody());
        $this->assertTrue($response->isSuccessful());
        $this->assertSame(200, $response->getHttpStatus());
        $this->assertSame('APPROVED', $response->getOrderStatus());
        $this->assertSame(self::ORDER_ID, $response->getTransactionReference());
        $this->assertSame('TXN_001', $response->getTransactionId());
        $this->assertNull($response->getCaptureStatus());
        $this->assertNull($response->getCaptureId());
    }

    #[Test]
    public function fetch_order_reads_the_capture_of_a_paid_order(): void
    {
        $this->setMockHttpResponse(['TokenSuccess.txt', 'OrderCompleted.txt']);

        $response = $this->gateway->fetchOrder(['transactionReference' => self::ORDER_ID])->send();

        $request = $this->sent();
        $this->assertSame('GET', $request->getMethod());
        $this->assertSame(self::ORDER_URL, (string) $request->getUri());
        $this->assertTrue($response->isSuccessful());
        $this->assertSame('COMPLETED', $response->getOrderStatus());
        $this->assertSame('COMPLETED', $response->getCaptureStatus());
        $this->assertSame('CAP_87654321', $response->getCaptureId());
    }

    #[Test]
    public function fetch_order_says_when_paypal_holds_no_such_order(): void
    {
        $this->setMockHttpResponse(['TokenSuccess.txt', 'OrderNotFound.txt']);

        $response = $this->gateway->fetchOrder(['transactionReference' => self::ORDER_ID])->send();

        $this->sent();
        $this->assertFalse($response->isSuccessful());
        $this->assertTrue($response->isUnknownOrder());
        $this->assertSame(404, $response->getHttpStatus());
        $this->assertSame('RESOURCE_NOT_FOUND', $response->getCode());
        $this->assertSame('The specified resource does not exist.', $response->getMessage());
        $this->assertNull($response->getOrderStatus());
    }

    #[Test]
    public function fetch_order_without_an_order_id_is_refused_before_sending(): void
    {
        $this->setMockHttpResponse('TokenSuccess.txt');

        try {
            $this->gateway->fetchOrder([])->send();
            $this->fail('An order read with no order id was sent.');
        } catch (InvalidRequestException $refused) {
            $this->assertSame('The transactionReference parameter is required', $refused->getMessage());
        }
        $this->assertOnlyTokenSent();
    }
}

<?php

declare(strict_types=1);

namespace Omnipay\PayPalV2\Tests\Endpoints;

use Omnipay\Common\Exception\InvalidRequestException;
use PHPUnit\Framework\Attributes\Test;

/**
 * Checkout against PayPal Orders v2's sandbox: creating the order the buyer approves at PayPal, and
 * capturing it when the buyer comes back, the way an app's return route does with PayPal's query
 * string. Each runs once for its success answer and once for each failure answer PayPal gives, and
 * a request missing what it needs is refused before it is sent.
 */
final class CheckoutTest extends EndpointTestCase
{
    private const string ORDER_ID = '5O190127TN364715T';

    #[Test]
    #[Calls('Gateway::purchase')]
    public function purchase_creates_the_order_and_redirects_the_buyer_to_approve_it(): void
    {
        $this->setMockHttpResponse(['TokenSuccess.txt', 'PurchaseSuccess.txt']);

        $response = $this->gateway->purchase([
            'amount' => '100.00',
            'currency' => 'USD',
            'transactionId' => 'TXN_001',
            'description' => 'Mentoring session',
            'returnUrl' => 'https://shop.example.com/paypal/return',
            'cancelUrl' => 'https://shop.example.com/paypal/cancel',
            'customerEmail' => 'buyer@example.com',
        ])->send();

        $request = $this->sent();
        $this->assertSame('POST', $request->getMethod());
        $this->assertSame('https://api-m.sandbox.paypal.com/v2/checkout/orders', (string) $request->getUri());
        $this->assertBearer($request);
        $this->assertSame([
            'intent' => 'CAPTURE',
            'purchase_units' => [[
                'reference_id' => 'TXN_001',
                'description' => 'Mentoring session',
                'invoice_id' => 'TXN_001',
                'amount' => [
                    'currency_code' => 'USD',
                    'value' => '100.00',
                    'breakdown' => ['item_total' => ['currency_code' => 'USD', 'value' => '100.00']],
                ],
                'items' => [[
                    'name' => 'Mentoring session',
                    'quantity' => '1',
                    'unit_amount' => ['currency_code' => 'USD', 'value' => '100.00'],
                ]],
            ]],
            'payment_source' => ['paypal' => [
                'experience_context' => [
                    'return_url' => 'https://shop.example.com/paypal/return',
                    'cancel_url' => 'https://shop.example.com/paypal/cancel',
                ],
                'email_address' => 'buyer@example.com',
            ]],
        ], $this->body($request));
        $this->assertFalse($response->isSuccessful(), 'An order awaiting approval is a redirect, not yet a success.');
        $this->assertTrue($response->isRedirect());
        $this->assertSame('https://www.sandbox.paypal.com/checkoutnow?token='.self::ORDER_ID, $response->getRedirectUrl());
        $this->assertSame(self::ORDER_ID, $response->getTransactionReference());
        $this->assertSame('TXN_001', $response->getTransactionId());
    }

    #[Test]
    public function purchase_reports_the_fields_paypal_rejects(): void
    {
        $this->setMockHttpResponse(['TokenSuccess.txt', 'PurchaseValidationError.txt']);

        $response = $this->gateway->purchase([
            'amount' => '100.00',
            'currency' => 'USD',
            'transactionId' => 'TXN_001',
            'returnUrl' => 'https://shop.example.com/paypal/return',
            'cancelUrl' => 'https://shop.example.com/paypal/cancel',
        ])->send();

        $this->sent();
        $this->assertFalse($response->isSuccessful());
        $this->assertFalse($response->isRedirect());
        $this->assertSame('The requested action could not be performed.', $response->getMessage());
        $this->assertSame('UNPROCESSABLE_ENTITY', $response->getCode());
    }

    #[Test]
    public function purchase_without_a_cancel_url_is_refused_before_sending(): void
    {
        $this->setMockHttpResponse('TokenSuccess.txt');

        try {
            $this->gateway->purchase([
                'amount' => '100.00',
                'currency' => 'USD',
                'transactionId' => 'TXN_001',
                'returnUrl' => 'https://shop.example.com/paypal/return',
            ])->send();
            $this->fail('A purchase with no cancelUrl was sent.');
        } catch (InvalidRequestException $refused) {
            $this->assertSame('The cancelUrl parameter is required', $refused->getMessage());
        }
        $this->assertOnlyTokenSent();
    }

    #[Test]
    #[Calls('Gateway::completePurchase')]
    public function complete_purchase_captures_the_approved_order(): void
    {
        $this->setMockHttpResponse(['TokenSuccess.txt', 'CaptureSuccess.txt']);

        $response = $this->gateway->completePurchase(['transactionReference' => self::ORDER_ID])->send();

        $request = $this->sent();
        $this->assertSame('POST', $request->getMethod());
        $this->assertSame('https://api-m.sandbox.paypal.com/v2/checkout/orders/'.self::ORDER_ID.'/capture', (string) $request->getUri());
        $this->assertBearer($request);
        $this->assertSame('{}', (string) $request->getBody());
        $this->assertTrue($response->isSuccessful());
        $this->assertSame('CAP_87654321', $response->getTransactionReference());
        $this->assertSame('TXN_001', $response->getTransactionId());
        $this->assertSame('COMPLETED', $response->getCaptureStatus());
    }

    #[Test]
    public function complete_purchase_captures_the_order_paypal_names_on_the_return_with_the_gateways_own_token(): void
    {
        $this->setMockHttpResponse(['TokenSuccess.txt', 'CaptureSuccess.txt']);

        // PayPal's return redirect carries ?token=<order id>&PayerID=..., and an app passes that
        // query string to completePurchase as it stands.
        $response = $this->gateway->completePurchase(['token' => self::ORDER_ID, 'PayerID' => 'PAYER123'])->send();

        $request = $this->sent();
        $this->assertSame('https://api-m.sandbox.paypal.com/v2/checkout/orders/'.self::ORDER_ID.'/capture', (string) $request->getUri());
        $this->assertBearer($request);
        $this->assertTrue($response->isSuccessful());
    }

    #[Test]
    public function complete_purchase_refuses_a_return_token_naming_another_order(): void
    {
        try {
            $this->gateway->completePurchase(['transactionReference' => self::ORDER_ID, 'token' => '8XY12345AB678901C'])->send();
            $this->fail('A capture naming two orders was sent.');
        } catch (InvalidRequestException $refused) {
            $this->assertSame(
                'PayPal returned the buyer for order 8XY12345AB678901C, but transactionReference names order '.self::ORDER_ID,
                $refused->getMessage(),
            );
        }
        $this->assertSame([], $this->getMockClient()->getRequests(), 'A capture naming two orders reached PayPal.');
    }

    #[Test]
    public function complete_purchase_reads_an_order_approved_but_not_captured_as_pending(): void
    {
        $this->setMockHttpResponse(['TokenSuccess.txt', 'CaptureApproved.txt']);

        $response = $this->gateway->completePurchase(['transactionReference' => self::ORDER_ID])->send();

        $this->sent();
        $this->assertFalse($response->isSuccessful());
        $this->assertTrue($response->isPending());
        $this->assertSame('Payment approved, awaiting capture', $response->getMessage());
    }

    #[Test]
    public function complete_purchase_reports_an_order_the_buyer_has_not_approved(): void
    {
        $this->setMockHttpResponse(['TokenSuccess.txt', 'CaptureError.txt']);

        $response = $this->gateway->completePurchase(['transactionReference' => self::ORDER_ID])->send();

        $this->sent();
        $this->assertFalse($response->isSuccessful());
        $this->assertFalse($response->isPending());
        $this->assertSame('The requested action could not be performed.', $response->getMessage());
        $this->assertSame('UNPROCESSABLE_ENTITY', $response->getCode());
    }
}

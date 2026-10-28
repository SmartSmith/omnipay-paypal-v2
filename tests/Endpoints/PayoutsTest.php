<?php

declare(strict_types=1);

namespace Omnipay\PayPalV2\Tests\Endpoints;

use Omnipay\Common\Exception\InvalidRequestException;
use PHPUnit\Framework\Attributes\Test;

/**
 * Payouts against PayPal's sandbox: sending money to an email address, and reading the payout
 * item's status. Each runs once for its success answer and once for each failure answer PayPal
 * gives, and a request missing what it needs is refused before it is sent.
 */
final class PayoutsTest extends EndpointTestCase
{
    #[Test]
    #[Calls('Gateway::payout')]
    public function payout_sends_the_amount_to_the_recipients_email(): void
    {
        $this->setMockHttpResponse(['TokenSuccess.txt', 'PayoutSuccess.txt']);

        $response = $this->gateway->payout([
            'amount' => '250.00',
            'currency' => 'USD',
            'recipientEmail' => 'mentor@example.com',
            'senderBatchId' => 'TXN_PAY_202602_001',
            'senderItemId' => 'TXN_PAY_202602_001',
            'emailSubject' => 'You have a payment',
            'note' => 'February sessions',
        ])->send();

        $request = $this->sent();
        $this->assertSame('POST', $request->getMethod());
        $this->assertSame('https://api-m.sandbox.paypal.com/v1/payments/payouts', (string) $request->getUri());
        $this->assertBearer($request);
        $this->assertSame([
            'sender_batch_header' => ['sender_batch_id' => 'TXN_PAY_202602_001', 'email_subject' => 'You have a payment'],
            'items' => [[
                'recipient_type' => 'EMAIL',
                'amount' => ['value' => '250.00', 'currency' => 'USD'],
                'receiver' => 'mentor@example.com',
                'sender_item_id' => 'TXN_PAY_202602_001',
                'note' => 'February sessions',
            ]],
        ], $this->body($request));
        $this->assertFalse($response->isSuccessful());
        $this->assertTrue($response->isPending());
        $this->assertSame('BATCH_PAY_001', $response->getTransactionReference());
        $this->assertSame('TXN_PAY_202602_001', $response->getTransactionId());
    }

    #[Test]
    public function payout_reports_the_field_paypal_rejects(): void
    {
        $this->setMockHttpResponse(['TokenSuccess.txt', 'PayoutError.txt']);

        $response = $this->gateway->payout([
            'amount' => '250.00',
            'currency' => 'USD',
            'recipientEmail' => 'mentor@example.com',
            'senderBatchId' => 'TXN_PAY_202602_001',
            'senderItemId' => 'TXN_PAY_202602_001',
        ])->send();

        $this->sent();
        $this->assertFalse($response->isSuccessful());
        $this->assertFalse($response->isPending());
        $this->assertSame('Invalid request - see details', $response->getMessage());
        $this->assertSame('VALIDATION_ERROR', $response->getCode());
    }

    #[Test]
    public function payout_without_a_recipient_is_refused_before_sending(): void
    {
        $this->setMockHttpResponse('TokenSuccess.txt');

        try {
            $this->gateway->payout(['amount' => '250.00', 'currency' => 'USD', 'senderBatchId' => 'B1', 'senderItemId' => 'I1'])->send();
            $this->fail('A payout with no recipientEmail was sent.');
        } catch (InvalidRequestException $refused) {
            $this->assertSame('The recipientEmail parameter is required', $refused->getMessage());
        }
        $this->assertOnlyTokenSent();
    }

    #[Test]
    #[Calls('Gateway::payoutStatus')]
    public function payout_status_reads_a_paid_item(): void
    {
        $this->setMockHttpResponse(['TokenSuccess.txt', 'PayoutItemSuccess.txt']);

        $response = $this->gateway->payoutStatus(['payoutItemId' => 'ITEM_PAY_001'])->send();

        $request = $this->sent();
        $this->assertSame('GET', $request->getMethod());
        $this->assertSame('https://api-m.sandbox.paypal.com/v1/payments/payouts-item/ITEM_PAY_001', (string) $request->getUri());
        $this->assertBearer($request);
        $this->assertTrue($response->isSuccessful());
        $this->assertSame('ITEM_PAY_001', $response->getTransactionReference());
        $this->assertSame('TXN_PAYPAL_001', $response->getPayPalTransactionId());
        $this->assertSame('TXN_PAY_202602_001', $response->getSenderItemId());
        $this->assertSame('BATCH_PAY_001', $response->getPayoutBatchId());
    }

    #[Test]
    public function payout_status_reads_an_item_still_in_flight_as_pending(): void
    {
        $this->setMockHttpResponse(['TokenSuccess.txt', 'PayoutItemPending.txt']);

        $response = $this->gateway->payoutStatus(['payoutItemId' => 'ITEM_PAY_001'])->send();

        $this->sent();
        $this->assertFalse($response->isSuccessful());
        $this->assertTrue($response->isPending());
        $this->assertSame('PENDING', $response->getTransactionStatus());
    }

    #[Test]
    public function payout_status_reads_a_failed_item_and_why(): void
    {
        $this->setMockHttpResponse(['TokenSuccess.txt', 'PayoutItemFailed.txt']);

        $response = $this->gateway->payoutStatus(['payoutItemId' => 'ITEM_PAY_002'])->send();

        $this->sent();
        $this->assertFalse($response->isSuccessful());
        $this->assertTrue($response->isFailed());
        $this->assertSame('Receiver is unregistered', $response->getMessage());
        $this->assertSame('RECEIVER_UNREGISTERED', $response->getCode());
    }

    #[Test]
    public function payout_status_without_an_item_id_is_refused_before_sending(): void
    {
        $this->setMockHttpResponse('TokenSuccess.txt');

        try {
            $this->gateway->payoutStatus()->send();
            $this->fail('A payoutStatus with no payoutItemId was sent.');
        } catch (InvalidRequestException $refused) {
            $this->assertSame('The payoutItemId parameter is required', $refused->getMessage());
        }
        $this->assertOnlyTokenSent();
    }
}

<?php

namespace Omnipay\PayPalV2\Message\Payout;

use Omnipay\Common\Message\AbstractResponse;

/**
 * PayPal Payout item status response.
 *
 * @see https://developer.paypal.com/docs/api/payments.payouts-batch/v1/#payouts-item_get
 */
class PayoutStatusResponse extends AbstractResponse
{
    public function isSuccessful(): bool
    {
        return $this->getTransactionStatus() === 'SUCCESS';
    }

    public function isPending(): bool
    {
        return in_array($this->getTransactionStatus(), ['PENDING', 'UNCLAIMED'], true);
    }

    public function isFailed(): bool
    {
        return in_array($this->getTransactionStatus(), ['FAILED', 'RETURNED', 'BLOCKED', 'REFUNDED', 'REVERSED'], true);
    }

    public function getTransactionStatus(): ?string
    {
        return $this->data['transaction_status'] ?? null;
    }

    public function getTransactionReference(): ?string
    {
        return $this->data['payout_item_id'] ?? null;
    }

    public function getPayPalTransactionId(): ?string
    {
        return $this->data['transaction_id'] ?? null;
    }

    public function getSenderItemId(): ?string
    {
        return $this->data['payout_item']['sender_item_id'] ?? null;
    }

    public function getPayoutBatchId(): ?string
    {
        return $this->data['payout_batch_id'] ?? null;
    }

    public function getMessage(): ?string
    {
        if (isset($this->data['errors']['message'])) {
            return $this->data['errors']['message'];
        }

        if (isset($this->data['message'])) {
            return $this->data['message'];
        }

        return null;
    }

    public function getCode(): ?string
    {
        return $this->data['errors']['name'] ?? $this->data['name'] ?? null;
    }
}

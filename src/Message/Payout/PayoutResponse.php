<?php

namespace Omnipay\PayPalV2\Message\Payout;

use Omnipay\Common\Message\AbstractResponse;

/**
 * PayPal Payouts batch creation response.
 *
 * A newly created payout batch starts in PENDING status.
 * The payout_batch_id is used to track the batch.
 *
 * @see https://developer.paypal.com/docs/api/payments.payouts-batch/v1/#payouts_post
 */
class PayoutResponse extends AbstractResponse
{
    public function isSuccessful(): bool
    {
        return isset($this->data['batch_header']['batch_status'])
            && $this->data['batch_header']['batch_status'] === 'SUCCESS';
    }

    public function isPending(): bool
    {
        return isset($this->data['batch_header']['batch_status'])
            && $this->data['batch_header']['batch_status'] === 'PENDING';
    }

    public function getTransactionReference(): ?string
    {
        return $this->data['batch_header']['payout_batch_id'] ?? null;
    }

    public function getTransactionId(): ?string
    {
        return $this->data['batch_header']['sender_batch_header']['sender_batch_id'] ?? null;
    }

    public function getBatchStatus(): ?string
    {
        return $this->data['batch_header']['batch_status'] ?? null;
    }

    public function getMessage(): ?string
    {
        if (isset($this->data['message'])) {
            return $this->data['message'];
        }

        if (isset($this->data['details'][0]['issue'])) {
            return $this->data['details'][0]['issue'];
        }

        return null;
    }

    public function getCode(): ?string
    {
        return $this->data['name'] ?? null;
    }
}

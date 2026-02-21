<?php

namespace Omnipay\PayPalV2\Message\Payout;

use Omnipay\PayPalV2\Message\AbstractRequest;

/**
 * Create a PayPal Payout batch.
 *
 * Sends money to a single recipient via the Payouts API.
 * Uses sender_batch_id for idempotency.
 *
 * @see https://developer.paypal.com/docs/api/payments.payouts-batch/v1/#payouts_post
 */
class PayoutRequest extends AbstractRequest
{
    public function getRecipientEmail(): ?string
    {
        return $this->getParameter('recipientEmail');
    }

    public function setRecipientEmail(?string $value): self
    {
        return $this->setParameter('recipientEmail', $value);
    }

    public function getSenderBatchId(): ?string
    {
        return $this->getParameter('senderBatchId');
    }

    public function setSenderBatchId(?string $value): self
    {
        return $this->setParameter('senderBatchId', $value);
    }

    public function getSenderItemId(): ?string
    {
        return $this->getParameter('senderItemId');
    }

    public function setSenderItemId(?string $value): self
    {
        return $this->setParameter('senderItemId', $value);
    }

    public function getEmailSubject(): ?string
    {
        return $this->getParameter('emailSubject');
    }

    public function setEmailSubject(?string $value): self
    {
        return $this->setParameter('emailSubject', $value);
    }

    public function getEmailMessage(): ?string
    {
        return $this->getParameter('emailMessage');
    }

    public function setEmailMessage(?string $value): self
    {
        return $this->setParameter('emailMessage', $value);
    }

    public function getNote(): ?string
    {
        return $this->getParameter('note');
    }

    public function setNote(?string $value): self
    {
        return $this->setParameter('note', $value);
    }

    public function getData(): array
    {
        $this->validate('amount', 'currency', 'recipientEmail', 'senderBatchId', 'senderItemId');

        $data = [
            'sender_batch_header' => [
                'sender_batch_id' => $this->getSenderBatchId(),
            ],
            'items' => [
                [
                    'recipient_type' => 'EMAIL',
                    'amount' => [
                        'value' => $this->getAmount(),
                        'currency' => $this->getCurrency(),
                    ],
                    'receiver' => $this->getRecipientEmail(),
                    'sender_item_id' => $this->getSenderItemId(),
                ],
            ],
        ];

        if ($this->getEmailSubject()) {
            $data['sender_batch_header']['email_subject'] = $this->getEmailSubject();
        }

        if ($this->getEmailMessage()) {
            $data['sender_batch_header']['email_message'] = $this->getEmailMessage();
        }

        if ($this->getNote()) {
            $data['items'][0]['note'] = $this->getNote();
        }

        return $data;
    }

    public function sendData($data): PayoutResponse
    {
        $endpoint = $this->getBaseEndpoint() . '/v1/payments/payouts';

        $httpResponse = $this->httpClient->request(
            'POST',
            $endpoint,
            $this->getAuthHeaders(),
            json_encode($data)
        );

        $responseBody = json_decode($httpResponse->getBody()->getContents(), true);

        return $this->response = new PayoutResponse($this, $responseBody ?? []);
    }
}

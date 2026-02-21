<?php

namespace Omnipay\PayPalV2\Message\Payout;

use Omnipay\PayPalV2\Message\AbstractRequest;

/**
 * Get the status of a PayPal Payout item.
 *
 * Queries individual payout item status by payout_item_id.
 *
 * @see https://developer.paypal.com/docs/api/payments.payouts-batch/v1/#payouts-item_get
 */
class PayoutStatusRequest extends AbstractRequest
{
    public function getPayoutItemId(): ?string
    {
        return $this->getParameter('payoutItemId');
    }

    public function setPayoutItemId(?string $value): self
    {
        return $this->setParameter('payoutItemId', $value);
    }

    public function getData(): array
    {
        $this->validate('payoutItemId');

        return [];
    }

    public function sendData($data): PayoutStatusResponse
    {
        $endpoint = $this->getBaseEndpoint() . '/v1/payments/payouts-item/' . $this->getPayoutItemId();

        $httpResponse = $this->httpClient->request(
            'GET',
            $endpoint,
            $this->getAuthHeaders()
        );

        $responseBody = json_decode($httpResponse->getBody()->getContents(), true);

        return $this->response = new PayoutStatusResponse($this, $responseBody ?? []);
    }
}

<?php

declare(strict_types=1);

namespace Omnipay\PayPalV2\Message;

/**
 * Read a checkout order as PayPal holds it, without changing it: one GET of the order. Use this to
 * ask whether a buyer paid; completePurchase() captures the order and so takes the money.
 *
 * @see https://developer.paypal.com/docs/api/orders/v2/#orders_get
 */
class FetchOrderRequest extends AbstractRequest
{
    /** @return array{order_id: string} */
    public function getData(): array
    {
        $this->validate('transactionReference');

        return ['order_id' => $this->getTransactionReference()];
    }

    /** @param array{order_id: string} $data */
    public function sendData($data): FetchOrderResponse
    {
        $endpoint = $this->getBaseEndpoint().'/v2/checkout/orders/'.urlencode($data['order_id']);

        $httpResponse = $this->httpClient->request('GET', $endpoint, $this->getAuthHeaders());

        $body = $httpResponse->getBody()->getContents();
        $decoded = json_decode($body, true);
        if (! is_array($decoded)) {
            $decoded = [];
        }

        return $this->response = new FetchOrderResponse($this, $decoded, $httpResponse->getStatusCode(), $body);
    }
}

<?php

namespace Omnipay\PayPalV2\Message;

use Omnipay\Common\Exception\InvalidRequestException;

class CompletePurchaseRequest extends AbstractRequest
{
    public function getData(): array
    {
        // PayPal order ID: from transactionReference (set explicitly)
        // or from the 'token' GET parameter (PayPal passes this on callback redirect)
        $orderId = $this->getTransactionReference() ?? $this->getParameter('token');

        if (!$orderId) {
            throw new InvalidRequestException('PayPal order ID is required (transactionReference or token parameter)');
        }

        return ['order_id' => $orderId];
    }

    public function sendData($data): CompletePurchaseResponse
    {
        $endpoint = $this->getBaseEndpoint()
            . '/v2/checkout/orders/'
            . urlencode($data['order_id'])
            . '/capture';

        $httpResponse = $this->httpClient->request(
            'POST',
            $endpoint,
            $this->getAuthHeaders(),
            '{}'
        );

        $responseBody = json_decode($httpResponse->getBody()->getContents(), true);

        return $this->response = new CompletePurchaseResponse($this, $responseBody ?? []);
    }
}

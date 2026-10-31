<?php

namespace Omnipay\PayPalV2\Message;

use Omnipay\Common\Exception\InvalidRequestException;

class CompletePurchaseRequest extends AbstractRequest
{
    public function getData(): array
    {
        // PayPal order ID: from transactionReference only. The 'token' parameter is the gateway's
        // OAuth access token, never an order id; Gateway::completePurchase() already maps the
        // return redirect's ?token=<order id> to transactionReference.
        $orderId = $this->getTransactionReference();

        if ($orderId === null || $orderId === '') {
            throw new InvalidRequestException('PayPal order ID is required as transactionReference');
        }

        return ['order_id' => $orderId];
    }

    public function sendData($data): CompletePurchaseResponse
    {
        $endpoint = $this->getBaseEndpoint()
            .'/v2/checkout/orders/'
            .urlencode($data['order_id'])
            .'/capture';

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

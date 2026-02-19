<?php

namespace Omnipay\PayPalV2\Message;

class TokenRequest extends AbstractRequest
{
    public function getData(): array
    {
        return [
            'grant_type' => 'client_credentials',
        ];
    }

    public function sendData($data): TokenResponse
    {
        $endpoint = $this->getBaseEndpoint() . '/v1/oauth2/token';

        $httpResponse = $this->httpClient->request(
            'POST',
            $endpoint,
            [
                'Authorization' => 'Basic ' . base64_encode($this->getClientId() . ':' . $this->getClientSecret()),
                'Content-Type' => 'application/x-www-form-urlencoded',
                'Accept' => 'application/json',
            ],
            http_build_query($data)
        );

        $responseBody = json_decode($httpResponse->getBody()->getContents(), true);

        return $this->response = new TokenResponse($this, $responseBody ?? []);
    }
}

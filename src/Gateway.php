<?php

namespace Omnipay\PayPalV2;

use Omnipay\Common\AbstractGateway;
use Omnipay\PayPalV2\Message\CompletePurchaseRequest;
use Omnipay\PayPalV2\Message\Payout\PayoutRequest;
use Omnipay\PayPalV2\Message\Payout\PayoutStatusRequest;
use Omnipay\PayPalV2\Message\PurchaseRequest;
use Omnipay\PayPalV2\Message\TokenRequest;

class Gateway extends AbstractGateway
{
    public function getName(): string
    {
        return 'PayPalV2';
    }

    public function getDefaultParameters(): array
    {
        return [
            'clientId' => '',
            'clientSecret' => '',
            'testMode' => false,
            'token' => '',
            'tokenExpires' => 0,
        ];
    }

    // -- Credentials --

    public function getClientId(): string
    {
        return $this->getParameter('clientId');
    }

    public function setClientId(string $value): self
    {
        return $this->setParameter('clientId', $value);
    }

    public function getClientSecret(): string
    {
        return $this->getParameter('clientSecret');
    }

    public function setClientSecret(string $value): self
    {
        return $this->setParameter('clientSecret', $value);
    }

    /**
     * Alias for setClientSecret() — backwards compatibility with configs that store 'secret'.
     */
    public function setSecret(string $value): self
    {
        return $this->setClientSecret($value);
    }

    // -- Token management --

    public function getToken(bool $createIfNeeded = true): string
    {
        if ($createIfNeeded && !$this->hasToken()) {
            $response = $this->createToken()->send();
            if ($response->isSuccessful()) {
                $this->setToken($response->getToken());
                $this->setTokenExpires(time() + $response->getExpires());
            }
        }

        return $this->getParameter('token') ?? '';
    }

    public function setToken(string $value): self
    {
        return $this->setParameter('token', $value);
    }

    public function getTokenExpires(): int
    {
        return (int) $this->getParameter('tokenExpires');
    }

    public function setTokenExpires(int $value): self
    {
        return $this->setParameter('tokenExpires', $value);
    }

    public function hasToken(): bool
    {
        $token = $this->getParameter('token');
        $expires = $this->getTokenExpires();

        return !empty($token) && time() < $expires;
    }

    public function createToken(): TokenRequest
    {
        return $this->createRequest(TokenRequest::class, []);
    }

    // -- Operations --

    public function purchase(array $parameters = []): PurchaseRequest
    {
        return $this->createRequest(PurchaseRequest::class, $parameters);
    }

    public function completePurchase(array $parameters = []): CompletePurchaseRequest
    {
        return $this->createRequest(CompletePurchaseRequest::class, $parameters);
    }

    // -- Payout operations --

    public function payout(array $parameters = []): PayoutRequest
    {
        return $this->createRequest(PayoutRequest::class, $parameters);
    }

    public function payoutStatus(array $parameters = []): PayoutStatusRequest
    {
        return $this->createRequest(PayoutStatusRequest::class, $parameters);
    }

    /**
     * Auto-acquire OAuth2 token before each non-token request.
     */
    public function createRequest($class, array $parameters = [])
    {
        if (!$this->hasToken() && $class !== TokenRequest::class) {
            $this->getToken(true);
        }

        return parent::createRequest($class, $parameters);
    }
}

<?php

namespace Omnipay\PayPalV2\Message;

use Omnipay\Common\Message\AbstractRequest as OmnipayAbstractRequest;

abstract class AbstractRequest extends OmnipayAbstractRequest
{
    protected const SANDBOX_ENDPOINT = 'https://api-m.sandbox.paypal.com';
    protected const PRODUCTION_ENDPOINT = 'https://api-m.paypal.com';

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

    public function getToken()
    {
        return $this->getParameter('token');
    }

    public function setToken($value)
    {
        return $this->setParameter('token', $value);
    }

    // -- Customer details --

    public function getCustomerEmail(): ?string
    {
        return $this->getParameter('customerEmail');
    }

    public function setCustomerEmail(string $value): self
    {
        return $this->setParameter('customerEmail', $value);
    }

    public function getCustomerFirstName(): ?string
    {
        return $this->getParameter('customerFirstName');
    }

    public function setCustomerFirstName(string $value): self
    {
        return $this->setParameter('customerFirstName', $value);
    }

    public function getCustomerLastName(): ?string
    {
        return $this->getParameter('customerLastName');
    }

    public function setCustomerLastName(string $value): self
    {
        return $this->setParameter('customerLastName', $value);
    }

    // -- Experience context --

    public function getLandingPage(): ?string
    {
        return $this->getParameter('landingPage');
    }

    public function setLandingPage(string $value): self
    {
        return $this->setParameter('landingPage', $value);
    }

    public function getShippingPreference(): ?string
    {
        return $this->getParameter('shippingPreference');
    }

    public function setShippingPreference(string $value): self
    {
        return $this->setParameter('shippingPreference', $value);
    }

    public function getBrandName(): ?string
    {
        return $this->getParameter('brandName');
    }

    public function setBrandName(string $value): self
    {
        return $this->setParameter('brandName', $value);
    }

    public function getUserAction(): ?string
    {
        return $this->getParameter('userAction');
    }

    public function setUserAction(string $value): self
    {
        return $this->setParameter('userAction', $value);
    }

    // -- Endpoints --

    protected function getBaseEndpoint(): string
    {
        return $this->getTestMode() ? self::SANDBOX_ENDPOINT : self::PRODUCTION_ENDPOINT;
    }

    protected function getAuthHeaders(): array
    {
        return [
            'Authorization' => 'Bearer ' . $this->getToken(),
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ];
    }
}

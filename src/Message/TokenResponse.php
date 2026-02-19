<?php

namespace Omnipay\PayPalV2\Message;

use Omnipay\Common\Message\AbstractResponse;

class TokenResponse extends AbstractResponse
{
    public function isSuccessful(): bool
    {
        return !empty($this->data['access_token']);
    }

    public function getToken(): ?string
    {
        return $this->data['access_token'] ?? null;
    }

    public function getExpires(): int
    {
        return (int) ($this->data['expires_in'] ?? 0);
    }

    public function getMessage(): ?string
    {
        return $this->data['error_description'] ?? $this->data['error'] ?? null;
    }

    public function getCode(): ?string
    {
        return $this->data['error'] ?? null;
    }
}

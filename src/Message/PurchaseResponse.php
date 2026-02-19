<?php

namespace Omnipay\PayPalV2\Message;

use Omnipay\Common\Message\AbstractResponse;
use Omnipay\Common\Message\RedirectResponseInterface;

class PurchaseResponse extends AbstractResponse implements RedirectResponseInterface
{
    public function isSuccessful(): bool
    {
        // Purchase always requires redirect for buyer approval
        return false;
    }

    public function isRedirect(): bool
    {
        return $this->getRedirectUrl() !== null;
    }

    public function getRedirectUrl(): ?string
    {
        if (!isset($this->data['links']) || !is_array($this->data['links'])) {
            return null;
        }

        foreach ($this->data['links'] as $link) {
            if (($link['rel'] ?? '') === 'payer-action') {
                return $link['href'];
            }
        }

        return null;
    }

    public function getRedirectMethod(): string
    {
        return 'GET';
    }

    public function getRedirectData(): ?array
    {
        return null;
    }

    public function getTransactionReference(): ?string
    {
        return $this->data['id'] ?? null;
    }

    public function getTransactionId(): ?string
    {
        $units = $this->data['purchase_units'] ?? [];

        return $units[0]['reference_id'] ?? null;
    }

    public function getOrderStatus(): ?string
    {
        return $this->data['status'] ?? null;
    }

    public function getMessage(): ?string
    {
        // Error responses have 'message' or 'details'
        if (isset($this->data['message'])) {
            return $this->data['message'];
        }

        if (isset($this->data['details']) && is_array($this->data['details'])) {
            $messages = array_map(
                fn($d) => ($d['field'] ?? '') . ': ' . ($d['description'] ?? $d['issue'] ?? ''),
                $this->data['details']
            );
            return implode('; ', $messages);
        }

        if (isset($this->data['status'])) {
            return 'Order status: ' . $this->data['status'];
        }

        return null;
    }

    public function getCode(): ?string
    {
        return $this->data['name'] ?? null;
    }
}

<?php

namespace Omnipay\PayPalV2\Message;

use Omnipay\Common\Message\AbstractResponse;

class CompletePurchaseResponse extends AbstractResponse
{
    public function isSuccessful(): bool
    {
        return ($this->data['status'] ?? '') === 'COMPLETED';
    }

    public function isPending(): bool
    {
        return in_array($this->data['status'] ?? '', ['APPROVED', 'PAYER_ACTION_REQUIRED'], true);
    }

    public function getTransactionReference(): ?string
    {
        // Return the capture ID — this is the actual payment reference for refunds etc.
        $units = $this->data['purchase_units'] ?? [];
        $captures = $units[0]['payments']['captures'] ?? [];

        return $captures[0]['id'] ?? $this->data['id'] ?? null;
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

    public function getCaptureStatus(): ?string
    {
        $units = $this->data['purchase_units'] ?? [];
        $captures = $units[0]['payments']['captures'] ?? [];

        return $captures[0]['status'] ?? null;
    }

    public function getMessage(): ?string
    {
        $status = $this->data['status'] ?? 'UNKNOWN';

        // Check for error responses
        if (isset($this->data['message'])) {
            return $this->data['message'];
        }

        if (isset($this->data['details']) && is_array($this->data['details'])) {
            $messages = array_map(
                fn($d) => ($d['description'] ?? $d['issue'] ?? ''),
                $this->data['details']
            );
            return implode('; ', $messages);
        }

        return match ($status) {
            'COMPLETED' => 'Payment captured successfully',
            'APPROVED' => 'Payment approved, awaiting capture',
            'VOIDED' => 'Order voided',
            default => "Order status: {$status}",
        };
    }

    public function getCode(): ?string
    {
        return $this->data['name'] ?? null;
    }
}

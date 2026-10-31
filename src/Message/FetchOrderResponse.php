<?php

declare(strict_types=1);

namespace Omnipay\PayPalV2\Message;

use Omnipay\Common\Message\AbstractResponse;
use Omnipay\Common\Message\RequestInterface;

/**
 * A checkout order as PayPal holds it. The order status says how far the buyer went (CREATED,
 * SAVED, APPROVED, VOIDED, COMPLETED, PAYER_ACTION_REQUIRED); once captured, the capture status
 * says whether the money moved (COMPLETED, PENDING, DECLINED, FAILED, REFUNDED,
 * PARTIALLY_REFUNDED). A 404 means PayPal holds no such order, for example one never approved and
 * since expired.
 *
 * @see https://developer.paypal.com/docs/api/orders/v2/#orders_get
 */
class FetchOrderResponse extends AbstractResponse
{
    /** @param array<array-key, mixed> $data */
    public function __construct(RequestInterface $request, $data, private readonly int $httpStatus, private readonly string $body)
    {
        parent::__construct($request, $data);
    }

    public function getHttpStatus(): int
    {
        return $this->httpStatus;
    }

    /** The body PayPal sent, as it came, for a caller to record when the order could not be read. */
    public function getBody(): string
    {
        return $this->body;
    }

    /** PayPal answered with the order. */
    public function isSuccessful(): bool
    {
        return $this->httpStatus === 200 && isset($this->data['status']);
    }

    /** PayPal holds no order by this id. */
    public function isUnknownOrder(): bool
    {
        return $this->httpStatus === 404;
    }

    public function getTransactionReference(): ?string
    {
        if (! isset($this->data['id'])) {
            return null;
        }

        return $this->data['id'];
    }

    public function getTransactionId(): ?string
    {
        if (! isset($this->data['purchase_units'][0]['reference_id'])) {
            return null;
        }

        return $this->data['purchase_units'][0]['reference_id'];
    }

    public function getOrderStatus(): ?string
    {
        if (! isset($this->data['status'])) {
            return null;
        }

        return $this->data['status'];
    }

    /** @return array<array-key, mixed>|null the first capture of the first purchase unit, once captured */
    private function capture(): ?array
    {
        if (! isset($this->data['purchase_units'][0]['payments']['captures'][0])) {
            return null;
        }

        return $this->data['purchase_units'][0]['payments']['captures'][0];
    }

    public function getCaptureId(): ?string
    {
        $capture = $this->capture();
        if ($capture === null || ! isset($capture['id'])) {
            return null;
        }

        return $capture['id'];
    }

    public function getCaptureStatus(): ?string
    {
        $capture = $this->capture();
        if ($capture === null || ! isset($capture['status'])) {
            return null;
        }

        return $capture['status'];
    }

    /** PayPal's error name, such as RESOURCE_NOT_FOUND, when it refused the read. */
    public function getCode(): ?string
    {
        if (! isset($this->data['name'])) {
            return null;
        }

        return $this->data['name'];
    }

    public function getMessage(): ?string
    {
        if (! isset($this->data['message'])) {
            return null;
        }

        return $this->data['message'];
    }
}

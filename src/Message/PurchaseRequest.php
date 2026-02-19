<?php

namespace Omnipay\PayPalV2\Message;

class PurchaseRequest extends AbstractRequest
{
    public function getData(): array
    {
        $this->validate('amount', 'currency', 'transactionId', 'returnUrl', 'cancelUrl');

        $amount = $this->getAmount();
        $currency = $this->getCurrency();
        $transactionId = $this->getTransactionId();

        // Build items
        $items = $this->getItems();
        $itemList = [];

        if ($items && count($items) > 0) {
            $itemTotal = '0.00';
            foreach ($items as $item) {
                $unitAmount = $this->formatCurrency($item->getPrice());
                $quantity = (string) $item->getQuantity();
                $itemList[] = [
                    'name' => $item->getName(),
                    'quantity' => $quantity,
                    'unit_amount' => [
                        'currency_code' => $currency,
                        'value' => $unitAmount,
                    ],
                ];
                $itemTotal = $this->formatCurrency(
                    (float) $itemTotal + ((float) $unitAmount * (int) $quantity)
                );
            }
        } else {
            // Construct single item from description and amount
            $itemList[] = [
                'name' => $this->getDescription() ?: 'Order ' . $transactionId,
                'quantity' => '1',
                'unit_amount' => [
                    'currency_code' => $currency,
                    'value' => $amount,
                ],
            ];
            $itemTotal = $amount;
        }

        // Build purchase unit
        $purchaseUnit = [
            'reference_id' => $transactionId,
            'description' => $this->getDescription() ?: 'Order ' . $transactionId,
            'invoice_id' => $transactionId,
            'amount' => [
                'currency_code' => $currency,
                'value' => $amount,
                'breakdown' => [
                    'item_total' => [
                        'currency_code' => $currency,
                        'value' => $itemTotal,
                    ],
                ],
            ],
            'items' => $itemList,
        ];

        // Build experience context
        $experienceContext = [
            'return_url' => $this->getReturnUrl(),
            'cancel_url' => $this->getCancelUrl(),
        ];

        if ($this->getLandingPage()) {
            $experienceContext['landing_page'] = $this->getLandingPage();
        }
        if ($this->getShippingPreference()) {
            $experienceContext['shipping_preference'] = $this->getShippingPreference();
        }
        if ($this->getUserAction()) {
            $experienceContext['user_action'] = $this->getUserAction();
        }
        if ($this->getBrandName()) {
            $experienceContext['brand_name'] = $this->getBrandName();
        }

        // Build payment source
        $paypalSource = [
            'experience_context' => $experienceContext,
        ];

        if ($this->getCustomerEmail()) {
            $paypalSource['email_address'] = $this->getCustomerEmail();
        }

        $firstName = $this->getCustomerFirstName();
        $lastName = $this->getCustomerLastName();
        if ($firstName || $lastName) {
            $name = [];
            if ($firstName) {
                $name['given_name'] = $firstName;
            }
            if ($lastName) {
                $name['surname'] = $lastName;
            }
            $paypalSource['name'] = $name;
        }

        return [
            'intent' => 'CAPTURE',
            'purchase_units' => [$purchaseUnit],
            'payment_source' => [
                'paypal' => $paypalSource,
            ],
        ];
    }

    public function sendData($data): PurchaseResponse
    {
        $endpoint = $this->getBaseEndpoint() . '/v2/checkout/orders';

        $httpResponse = $this->httpClient->request(
            'POST',
            $endpoint,
            $this->getAuthHeaders(),
            json_encode($data)
        );

        $responseBody = json_decode($httpResponse->getBody()->getContents(), true);

        return $this->response = new PurchaseResponse($this, $responseBody ?? []);
    }
}

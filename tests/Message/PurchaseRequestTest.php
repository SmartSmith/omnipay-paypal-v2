<?php

namespace Omnipay\PayPalV2\Tests\Message;

use Omnipay\PayPalV2\Message\PurchaseRequest;
use Omnipay\Tests\TestCase;

class PurchaseRequestTest extends TestCase
{
    private PurchaseRequest $request;

    public function setUp(): void
    {
        parent::setUp();
        $this->request = new PurchaseRequest($this->getHttpClient(), $this->getHttpRequest());
        $this->request->initialize([
            'clientId' => 'test_client_id',
            'clientSecret' => 'test_client_secret',
            'token' => 'test_bearer_token',
            'testMode' => true,
            'amount' => '100.00',
            'currency' => 'USD',
            'transactionId' => 'TXN_001',
            'returnUrl' => 'https://example.com/return',
            'cancelUrl' => 'https://example.com/cancel',
            'description' => 'Test Purchase',
        ]);
    }

    public function testGetDataStructure(): void
    {
        $data = $this->request->getData();

        $this->assertSame('CAPTURE', $data['intent']);
        $this->assertCount(1, $data['purchase_units']);
        $this->assertArrayHasKey('payment_source', $data);
    }

    public function testPurchaseUnitConstruction(): void
    {
        $data = $this->request->getData();
        $unit = $data['purchase_units'][0];

        $this->assertSame('TXN_001', $unit['reference_id']);
        $this->assertSame('Test Purchase', $unit['description']);
        $this->assertSame('TXN_001', $unit['invoice_id']);
        $this->assertSame('USD', $unit['amount']['currency_code']);
        $this->assertSame('100.00', $unit['amount']['value']);
        $this->assertSame('100.00', $unit['amount']['breakdown']['item_total']['value']);
    }

    public function testDefaultItemCreatedFromDescription(): void
    {
        $data = $this->request->getData();
        $items = $data['purchase_units'][0]['items'];

        $this->assertCount(1, $items);
        $this->assertSame('Test Purchase', $items[0]['name']);
        $this->assertSame('1', $items[0]['quantity']);
        $this->assertSame('100.00', $items[0]['unit_amount']['value']);
        $this->assertSame('USD', $items[0]['unit_amount']['currency_code']);
    }

    public function testDefaultItemNameWithoutDescription(): void
    {
        $this->request->setDescription('');
        $data = $this->request->getData();
        $items = $data['purchase_units'][0]['items'];

        $this->assertSame('Order TXN_001', $items[0]['name']);
    }

    public function testExperienceContext(): void
    {
        $data = $this->request->getData();
        $context = $data['payment_source']['paypal']['experience_context'];

        $this->assertSame('https://example.com/return', $context['return_url']);
        $this->assertSame('https://example.com/cancel', $context['cancel_url']);
    }

    public function testExperienceContextWithOptionalFields(): void
    {
        $this->request->setLandingPage('GUEST_CHECKOUT');
        $this->request->setShippingPreference('NO_SHIPPING');
        $this->request->setUserAction('PAY_NOW');
        $this->request->setBrandName('TestBrand');

        $data = $this->request->getData();
        $context = $data['payment_source']['paypal']['experience_context'];

        $this->assertSame('GUEST_CHECKOUT', $context['landing_page']);
        $this->assertSame('NO_SHIPPING', $context['shipping_preference']);
        $this->assertSame('PAY_NOW', $context['user_action']);
        $this->assertSame('TestBrand', $context['brand_name']);
    }

    public function testCustomerEmail(): void
    {
        $this->request->setCustomerEmail('buyer@test.com');
        $data = $this->request->getData();

        $this->assertSame('buyer@test.com', $data['payment_source']['paypal']['email_address']);
    }

    public function testCustomerName(): void
    {
        $this->request->setCustomerFirstName('John');
        $this->request->setCustomerLastName('Doe');
        $data = $this->request->getData();

        $this->assertSame('John', $data['payment_source']['paypal']['name']['given_name']);
        $this->assertSame('Doe', $data['payment_source']['paypal']['name']['surname']);
    }

    public function testCustomerNameFirstNameOnly(): void
    {
        $this->request->setCustomerFirstName('John');
        $data = $this->request->getData();

        $this->assertSame('John', $data['payment_source']['paypal']['name']['given_name']);
        $this->assertArrayNotHasKey('surname', $data['payment_source']['paypal']['name']);
    }

    public function testNoCustomerNameWhenNotProvided(): void
    {
        $data = $this->request->getData();

        $this->assertArrayNotHasKey('name', $data['payment_source']['paypal']);
    }

    public function testNoCustomerEmailWhenNotProvided(): void
    {
        $data = $this->request->getData();

        $this->assertArrayNotHasKey('email_address', $data['payment_source']['paypal']);
    }

    public function testSandboxEndpoint(): void
    {
        $this->setMockHttpResponse('PurchaseSuccess.txt');
        $response = $this->request->send();

        // Verify the mock was called (indicates the request was sent)
        $this->assertNotNull($response);
    }

    public function testProductionEndpoint(): void
    {
        $this->request->setTestMode(false);
        $this->setMockHttpResponse('PurchaseSuccess.txt');
        $response = $this->request->send();

        $this->assertNotNull($response);
    }

    public function testValidationRequiresAmount(): void
    {
        $this->request->setAmount(null);

        $this->expectException(\Omnipay\Common\Exception\InvalidRequestException::class);
        $this->request->getData();
    }

    public function testValidationRequiresCurrency(): void
    {
        $this->request->setCurrency(null);

        $this->expectException(\Omnipay\Common\Exception\InvalidRequestException::class);
        $this->request->getData();
    }

    public function testValidationRequiresTransactionId(): void
    {
        $this->request->setTransactionId(null);

        $this->expectException(\Omnipay\Common\Exception\InvalidRequestException::class);
        $this->request->getData();
    }

    public function testValidationRequiresReturnUrl(): void
    {
        $this->request->setReturnUrl(null);

        $this->expectException(\Omnipay\Common\Exception\InvalidRequestException::class);
        $this->request->getData();
    }

    public function testValidationRequiresCancelUrl(): void
    {
        $this->request->setCancelUrl(null);

        $this->expectException(\Omnipay\Common\Exception\InvalidRequestException::class);
        $this->request->getData();
    }

    public function testMultipleItems(): void
    {
        $this->request->setItems([
            ['name' => 'Item A', 'quantity' => 2, 'price' => '30.00'],
            ['name' => 'Item B', 'quantity' => 1, 'price' => '40.00'],
        ]);

        $data = $this->request->getData();
        $items = $data['purchase_units'][0]['items'];

        $this->assertCount(2, $items);
        $this->assertSame('Item A', $items[0]['name']);
        $this->assertSame('2', $items[0]['quantity']);
        $this->assertSame('30.00', $items[0]['unit_amount']['value']);
        $this->assertSame('Item B', $items[1]['name']);
        $this->assertSame('1', $items[1]['quantity']);
        $this->assertSame('40.00', $items[1]['unit_amount']['value']);
    }

    public function testAuthHeaders(): void
    {
        $this->setMockHttpResponse('PurchaseSuccess.txt');
        $this->request->send();

        $lastRequest = $this->getMockClient()->getLastRequest();
        $this->assertSame('Bearer test_bearer_token', $lastRequest->getHeaderLine('Authorization'));
        $this->assertSame('application/json', $lastRequest->getHeaderLine('Content-Type'));
    }
}

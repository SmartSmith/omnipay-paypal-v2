<?php

declare(strict_types=1);

namespace Omnipay\PayPalV2\Tests\Endpoints;

use Omnipay\PayPalV2\Gateway;
use PHPUnit\Framework\Attributes\Test;
use ReflectionClass;
use ReflectionMethod;

/**
 * The set calls every operation the Gateway declares: each public method of Gateway is named by
 * exactly one test's #[Calls], so removing that test turns this red, or is listed below as not an
 * operation, with why. Every #[Calls] names a real method, and every listed method still exists.
 */
final class EveryEntryPointIsCalledTest extends EndpointTestCase
{
    /** @var array<string, string> each public Gateway method that sends nothing, and why */
    private const array NOT_OPERATIONS = [
        'getName' => 'the gateway name Omnipay\'s factory reports',
        'getDefaultParameters' => 'the parameter defaults initialize() reads',
        'getClientId' => 'a credential accessor; every token request test asserts the Basic header it fills',
        'setClientId' => 'a credential accessor; every token request test asserts the Basic header it fills',
        'getClientSecret' => 'a credential accessor; every token request test asserts the Basic header it fills',
        'setClientSecret' => 'a credential accessor; every token request test asserts the Basic header it fills',
        'setSecret' => 'the old name for setClientSecret, kept for apps that store `secret`',
        'setToken' => 'a token accessor getToken fills; every operation test asserts the bearer it carries',
        'getTokenExpires' => 'a token accessor getToken fills; the getToken test reads it',
        'setTokenExpires' => 'a token accessor getToken fills; every operation test asserts the bearer it carries',
        'hasToken' => 'whether the kept token is still good; the getToken test reads it',
        'createRequest' => 'the factory every operation goes through, fetching the token first; every operation test calls it',
    ];

    #[Test]
    public function every_entry_point_is_called_once_and_every_call_names_one(): void
    {
        $declared = $this->declared();
        $entries = array_map(static fn (string $method): string => 'Gateway::'.$method, array_values(array_diff($declared, array_keys(self::NOT_OPERATIONS))));
        $called = self::called();

        $this->assertSame([], array_values(array_diff(array_keys(self::NOT_OPERATIONS), $declared)), 'These listed methods are no longer on Gateway.');
        $this->assertSame([], array_values(array_diff($entries, array_keys($called))), 'These entry points have no end-to-end test.');
        $this->assertSame([], array_values(array_diff(array_keys($called), $entries)), 'These #[Calls] name no entry point.');
        foreach ($called as $entry => $tests) {
            $this->assertCount(1, $tests, $entry.' is named by '.implode(', ', $tests).'.');
        }
    }

    /** @return list<string> the public methods Gateway itself declares */
    private function declared(): array
    {
        $methods = [];
        foreach ((new ReflectionClass(Gateway::class))->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
            if ($method->getDeclaringClass()->getName() === Gateway::class && ! $method->isStatic()) {
                $methods[] = $method->getName();
            }
        }
        $this->assertNotSame([], $methods, 'Gateway declares no public method.');

        return $methods;
    }

    /** @return array<string, list<string>> each named entry point, with the tests that name it */
    private static function called(): array
    {
        $called = [];
        foreach (glob(__DIR__.'/*Test.php') as $file) {
            $class = __NAMESPACE__.'\\'.basename($file, '.php');
            foreach ((new ReflectionClass($class))->getMethods() as $method) {
                foreach ($method->getAttributes(Calls::class) as $attribute) {
                    $called[$attribute->newInstance()->entry][] = basename($file, '.php').'::'.$method->getName();
                }
            }
        }

        return $called;
    }
}

<?php

declare( strict_types=1 );

namespace Kama\LiteWireDI\Tests;

use PHPUnit\Framework\TestCase;
use Kama\LiteWireDI\Container;
use Kama\LiteWireDI\Tests\Fixtures\AbstractService;
use Kama\LiteWireDI\Tests\Fixtures\ClassCyclicA;
use Kama\LiteWireDI\Tests\Fixtures\ClassNeedsInterface;
use Kama\LiteWireDI\Tests\Fixtures\ClassPrivateConstructor;
use Kama\LiteWireDI\Tests\Fixtures\ClassWithScalarRequired;
use Kama\LiteWireDI\Tests\Fixtures\InterfaceImpl;
use Kama\LiteWireDI\Tests\Fixtures\SimpleClass;
use Kama\LiteWireDI\Tests\Fixtures\SomeInterface;

/**
 * Tests for Container::has()
 *
 * - Registered service → true
 * - Unregistered → false
 * - Existing autowireable class before get() → true
 * - After auto-resolve via get() → true
 * - Container itself → true
 */
final class HasTest extends TestCase {

	private Container $container;

	protected function setUp(): void {
		$this->container = new Container();
	}

	public function test__registered_service(): void {
		$this->container->set( SimpleClass::class, new SimpleClass() );

		self::assertTrue( $this->container->has( SimpleClass::class ) );
	}

	public function test__unregistered_service(): void {
		self::assertFalse( $this->container->has( 'not-exist' ) );
	}

	public function test__existing_class_before_get(): void {
		self::assertTrue( $this->container->has( SimpleClass::class ) );
	}

	public function test__after_get_unregistered_class(): void {
		$this->container->get( SimpleClass::class );

		self::assertTrue( $this->container->has( SimpleClass::class ) );
	}

	public function test__container_itself(): void {
		self::assertTrue( $this->container->has( Container::class ) );
	}

	// ────────────────────────────────────────────────────────────────────
	// can_resolve_class
	// ────────────────────────────────────────────────────────────────────

	public function test__abstract_class_is_not_autowireable(): void {
		self::assertFalse( $this->container->has( AbstractService::class ) );
	}

	public function test__class_with_private_constructor_is_not_autowireable(): void {
		self::assertFalse( $this->container->has( ClassPrivateConstructor::class ) );
	}

	public function test__class_with_required_scalar_is_not_autowireable(): void {
		self::assertFalse( $this->container->has( ClassWithScalarRequired::class ) );
	}

	public function test__class_with_unbound_interface_is_not_autowireable(): void {
		self::assertFalse( $this->container->has( ClassNeedsInterface::class ) );
	}

	public function test__class_with_bound_interface_is_autowireable(): void {
		$this->container->set( SomeInterface::class, InterfaceImpl::class );

		self::assertTrue( $this->container->has( ClassNeedsInterface::class ) );
	}

	public function test__class_with_circular_dependencies_is_not_autowireable(): void {
		self::assertFalse( $this->container->has( ClassCyclicA::class ) );
	}

}

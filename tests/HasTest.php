<?php

declare( strict_types=1 );

namespace Kama\MiniContainer\Tests;

use PHPUnit\Framework\TestCase;
use Kama\MiniContainer\Container;
use Kama\MiniContainer\Tests\Fixtures\SimpleClass;

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
		$this->container->set( 'service', new SimpleClass() );

		self::assertTrue( $this->container->has( 'service' ) );
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
}

<?php

declare( strict_types=1 );

namespace Kama\LiteWireDI\Tests;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Kama\LiteWireDI\Container;
use Kama\LiteWireDI\Tests\Fixtures\SimpleClass;
use stdClass;

final class SetTest extends TestCase {

	private Container $container;

	protected function setUp(): void {
		$this->container = new Container();
	}

	// ────────────────────────────────────────────────────────────────────
	// Register
	// ────────────────────────────────────────────────────────────────────

	public function test__register_object(): void {
		$obj = new SimpleClass();
		$this->container->set( SimpleClass::class, $obj );

		self::assertTrue( $this->container->has( SimpleClass::class ) );
	}

	public function test__register_class_string(): void {
		$this->container->set( SimpleClass::class, SimpleClass::class );

		self::assertTrue( $this->container->has( SimpleClass::class ) );
	}

	public function test__register_closure(): void {
		$this->container->set( SimpleClass::class, function () {
			return new SimpleClass();
		} );

		self::assertTrue( $this->container->has( SimpleClass::class ) );
	}

	// ────────────────────────────────────────────────────────────────────
	// Overwrite
	// ────────────────────────────────────────────────────────────────────

	public function test__overwrite_with_same_definition(): void {
		$this->container->set( SimpleClass::class, SimpleClass::class );
		$first = $this->container->get( SimpleClass::class );

		$this->container->set( SimpleClass::class, SimpleClass::class );
		$second = $this->container->get( SimpleClass::class );

		self::assertNotSame( $first, $second );
		self::assertEquals( $first, $second );
	}

	public function test__overwrite_with_different_definition(): void {
		$this->container->set( SimpleClass::class, new SimpleClass() );
		$this->container->set( SimpleClass::class, new stdClass() );

		self::assertInstanceOf( stdClass::class, $this->container->get( SimpleClass::class ) );
	}

	// ────────────────────────────────────────────────────────────────────
	// Exception
	// ────────────────────────────────────────────────────────────────────

	public function test__exception_on_primitive_int(): void {
		$this->expectException( InvalidArgumentException::class );

		$this->container->set( SimpleClass::class, 123 );
	}

	public function test__exception_on_primitive_array(): void {
		$this->expectException( InvalidArgumentException::class );

		$this->container->set( SimpleClass::class, [ 'foo' ] );
	}

	public function test__exception_on_primitive_bool(): void {
		$this->expectException( InvalidArgumentException::class );

		$this->container->set( SimpleClass::class, true );
	}

	public function test__exception_on_unknown_class_string(): void {
		$this->expectException( InvalidArgumentException::class );
		$this->expectExceptionMessageIsOrContains( 'does not exist' );

		$this->container->set( SimpleClass::class, 'UnknownClass' );
	}

	public function test__exception_on_string_id(): void {
		$this->expectException( InvalidArgumentException::class );
		$this->expectExceptionMessageIsOrContains( 'must be an existing class or interface' );

		$this->container->set( 'service', SimpleClass::class ); // @phpstan-ignore argument.type
	}

}

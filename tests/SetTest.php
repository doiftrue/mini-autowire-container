<?php

declare( strict_types=1 );

namespace Kama\MiniContainer\Tests;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Kama\MiniContainer\Container;
use Kama\MiniContainer\Tests\Fixtures\SimpleClass;
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
		$this->container->set( 'service', SimpleClass::class );

		self::assertTrue( $this->container->has( 'service' ) );
	}

	public function test__register_closure(): void {
		$this->container->set( 'service', function () {
			return new SimpleClass();
		} );

		self::assertTrue( $this->container->has( 'service' ) );
	}

	// ────────────────────────────────────────────────────────────────────
	// Overwrite
	// ────────────────────────────────────────────────────────────────────

	public function test__overwrite_with_same_definition(): void {
		$this->container->set( 'service', SimpleClass::class );
		$first = $this->container->get( 'service' );

		$this->container->set( 'service', SimpleClass::class );
		$second = $this->container->get( 'service' );

		self::assertNotSame( $first, $second );
		self::assertEquals( $first, $second );
	}

	public function test__overwrite_with_different_definition(): void {
		$this->container->set( 'service', new SimpleClass() );
		$this->container->set( 'service', new stdClass() );

		self::assertInstanceOf( stdClass::class, $this->container->get( 'service' ) );
	}

	// ────────────────────────────────────────────────────────────────────
	// Exception
	// ────────────────────────────────────────────────────────────────────

	public function test__exception_on_primitive_int(): void {
		$this->expectException( InvalidArgumentException::class );

		$this->container->set( 'service', 123 );
	}

	public function test__exception_on_primitive_array(): void {
		$this->expectException( InvalidArgumentException::class );

		$this->container->set( 'service', [ 'foo' ] );
	}

	public function test__exception_on_primitive_bool(): void {
		$this->expectException( InvalidArgumentException::class );

		$this->container->set( 'service', true );
	}

}

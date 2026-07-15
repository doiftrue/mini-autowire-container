<?php // phpcs:ignoreFile
/**
 * Single-file DI Container with autowiring for PHP applications.
 * PSR-11-style API. No dependencies. Especially handy for WordPress plugins and themes.
 *
 * License: MIT License
 *
 * Author: Timur Kamaev
 * Author Site: https://wp-kama.com/
 * Source: https://github.com/doiftrue/litewire-di
 * Original Idea: Andrei Pisarevskii (https://github.com/renakdup/simple-dic)
 *
 * Version: 1.3.0
 */

namespace Kama\LiteWireDI;

use ReflectionClass;
use ReflectionFunction;
use ReflectionException;
use InvalidArgumentException;
use RuntimeException;
use ReflectionNamedType;
use ReflectionParameter;
use Closure;

use function array_key_exists;
use function class_exists;
use function interface_exists;
use function is_string;

class ContainerException extends RuntimeException {}
class NotFoundException extends ContainerException {}

class Container {

	/**
	 * Service definitions (creation rules).
	 *
	 * @var array<class-string, object|Closure|class-string|array<string, mixed>>
	 */
	protected array $definitions = [];

	/**
	 * Resolved shared instances.
	 *
	 * @var array<class-string, object>
	 */
	protected array $instances = [];

	/**
	 * ReflectionClass cache (for autowiring).
	 *
	 * @var array<class-string, ReflectionClass<object>>
	 */
	protected array $reflection_cache = [];

	/**
	 * Entry IDs currently being resolved (cycle detection).
	 *
	 * @var array<class-string, true>
	 */
	protected array $resolving = [];


	/**
	 * Makes the container itself available as a dependency.
	 */
	public function __construct() {
		$this->instances[ self::class ] = $this;
	}

	/**
	 * Checks whether the container already knows about the service:
	 * it was registered with set(), previously created and stored,
	 * or is an existing class that can be resolved automatically.
	 *
	 * @param string $id Identifier of the entry to look for.
	 */
	public function has( string $id ): bool {
		return isset( $this->instances[ $id ] )
			|| isset( $this->definitions[ $id ] )
			|| ( class_exists( $id ) && $this->can_resolve_class( $id ) );
	}

	/**
	 * Registers a service. The service may be an existing object,
	 * a class name, a factory (closure) that creates it, or named constructor
	 * parameters for the class identified by $id.
	 * Replacing an existing service removes its stored instance.
	 *
	 * @param class-string $id Identifier of the entry to look for.
	 * @param object|Closure|class-string|array<string, mixed> $service  Service definition, class name, ready instance, factory,
	 *                                                                   or named constructor parameters.
	 *
	 * @phpstan-param mixed $service Runtime validation intentionally accepts an unchecked input.
	 *
	 * @throws InvalidArgumentException
	 */
	public function set( string $id, $service ): void {
		if ( ! $this->is_valid_id( $id ) ) {
			throw new InvalidArgumentException( "Service ID `$id` must be an existing class or interface." );
		}

		if ( is_array( $service ) ) {
			if ( ! class_exists( $id ) || ! $this->get_reflection( $id )->isInstantiable() ) {
				throw new InvalidArgumentException(
					"Container::set( ID ): ID require the service ID to be an instantiable class. ID = `$id`"
				);
			}

			foreach ( array_keys( $service ) as $name ) {
				if ( ! is_string( $name ) ) {
					throw new InvalidArgumentException(
						"Container::set( ID, SERVICE ): SERVICE parameter must be an associative array keyed by parameter name. ID = `$id`"
					);
				}
			}
		}
		elseif ( ! is_object( $service ) && ! is_string( $service ) ) {
			throw new InvalidArgumentException(
				"Container::set( ID ): ID must be an object, class name, factory, or configured parameters. ID = `$id`"
			);
		}

		if ( is_string( $service ) && ! class_exists( $service ) ) {
			throw new InvalidArgumentException( "Class `$service` configured for service `$id` does not exist." );
		}

		$this->definitions[ $id ] = $service;
		unset( $this->instances[ $id ] );
	}

	/**
	 * Loads a registered service or automatically creates the requested class.
	 * Stores the resolved service as a shared instance for later calls.
	 *
	 * @template TService of object
	 * @param class-string<TService> $id Identifier of the entry to look for.
	 *
	 * @return TService NOTE: Do not add a native return type. Declaring `: object` prevents PhpStorm
	 *                  from reliably inferring the concrete return type from `class-string<TService>`.
	 *
	 * @throws NotFoundException No entry was found for identifier.
	 * @throws ContainerException Error while retrieving the entry.
	 */
	public function get( string $id ) {
		if ( ! $this->is_valid_id( $id ) ) {
			throw new NotFoundException( "Service ID `$id` must be an existing class or interface." );
		}

		if ( isset( $this->instances[ $id ] ) ) {
			/** @var TService */
			return $this->instances[ $id ];
		}

		$this->begin_resolving( $id );
		try {
			/** @var TService */
			return $this->instances[ $id ] = $this->resolve( $id );
		}
		finally {
			$this->end_resolving( $id );
		}
	}

	/**
	 * Creates a class instance from a registered definition or class name.
	 * Unlike get(), it does not store the result as a shared instance.
	 * Named parameters can be used to provide specific values for constructor arguments.
	 *
	 * @template TService of object
	 * @param class-string<TService> $id         Identifier of the entry to look for.
	 * @param array<string, mixed> $parameters Named parameters for the constructor.
	 *
	 * @return TService NOTE: Do not add a native return type. Declaring `: object` prevents PhpStorm
	 *                  from reliably inferring the concrete return type from `class-string<TService>`.
	 *
	 * @throws ReflectionException
	 * @throws ContainerException
	 */
	public function make( string $id, array $parameters = [] ) {
		if ( ! $this->is_valid_id( $id ) ) {
			throw new NotFoundException( "Service ID `$id` must be an existing class or interface." );
		}

		$this->begin_resolving( $id );
		try {
			$def = $this->definitions[ $id ] ?? $id;

			if( $def instanceof Closure ){
				/** @var TService $instance */
				$instance = $this->invoke_factory( $id, $def, $parameters );
				return $instance;
			}

			if( is_object( $def ) ){
				throw new ContainerException(
					"Service `$id` is registered as an instance and cannot be created with make()."
				);
			}

			if ( is_array( $def ) ) {
				/** @var TService */
				return $this->resolve_class( $id, array_replace( $def, $parameters ) );
			}

			/** @var class-string $def The definition is guaranteed to be an existing class name. */
			/** @var TService */
			return $this->resolve_class( $def, $parameters );
		}
		finally {
			$this->end_resolving( $id );
		}
	}

	/**
	 * @throws ContainerException
	 * @throws NotFoundException
	 */
	protected function resolve( string $id ): object {
		$def = $this->definitions[ $id ] ?? null;
		if ( $def ) {
			if ( $def instanceof Closure ) {
				return $this->invoke_factory( $id, $def );
			}

			if ( is_object( $def ) ) {
				return $def;
			}

			// $def is named parameters for the constructor.
			if ( is_array( $def ) ) {
				/** @var class-string $id */
				return $this->resolve_class( $id, $def );
			}

			/** @var class-string $def At this point the definition is guaranteed exists. */
			return $this->resolve_class( $def );
		}

		if ( class_exists( $id ) ) {
			return $this->resolve_class( $id );
		}

		throw new NotFoundException( "Service `$id` not found in the Container." );
	}

	/**
	 * Invokes a factory with autowired and explicitly provided parameters.
	 *
	 * @param array<string, mixed> $runtime_params  Runtime parameters by name.
	 *
	 * @throws ReflectionException
	 * @throws ContainerException
	 */
	protected function invoke_factory( string $id, Closure $factory, array $runtime_params = [] ): object {
		$reflection = new ReflectionFunction( $factory );
		$service = $factory( ...$this->resolve_parameters( $reflection->getParameters(), $runtime_params ) );

		if ( ! is_object( $service ) ) {
			throw new ContainerException( "Factory for service `$id` must return an object." );
		}

		return $service;
	}

	/**
	 * Creates a class instance and fills in its constructor arguments.
	 * Explicitly provided arguments take priority; the rest are resolved automatically.
	 *
	 * @param class-string         $class
	 * @param array<string, mixed> $runtime_params  Runtime constructor parameters by name.
	 *
	 * @throws ContainerException
	 */
	protected function resolve_class( string $class, array $runtime_params = [] ): object {
		try {
			$reflection = $this->get_reflection( $class );

			if ( ! $reflection->isInstantiable() ) {
				throw new ContainerException( "Class `$class` is not instantiable." );
			}

			$constructor = $reflection->getConstructor();
			if ( ! $constructor ) {
				if ( $runtime_params ) {
					throw new ContainerException( "Class `$class` has no constructor and does not accept parameters." );
				}

				return new $class();
			}

			$params = $constructor->getParameters();
			$resolved_params = $this->resolve_parameters( $params, $runtime_params );
		}
		// Generally this catch cannot be reached through the public API, but better have it just in case.
		// @codeCoverageIgnoreStart
		catch ( ReflectionException $e ) {
			throw new ContainerException( "Service `$class` could not be resolved due the reflection issue: `{$e->getMessage()}`" );
		}
		// @codeCoverageIgnoreEnd

		return new $class( ...$resolved_params );
	}

	/**
	 * Prepares arguments for a constructor or factory in the required order.
	 * Uses named runtime values first and resolves any remaining arguments automatically.
	 *
	 * @param list<ReflectionParameter> $params
	 * @param array<string, mixed>  $runtime_params  Runtime parameters by name.
	 *
	 * @return list<mixed>
	 *
	 * @throws ContainerException
	 * @throws ReflectionException
	 */
	protected function resolve_parameters( array $params, array $runtime_params = [] ): array {
		$this->validate_parameters( $params, $runtime_params );

		$resolved = [];
		foreach ( $params as $param ) {
			$name = $param->getName();
			$resolved[] = array_key_exists( $name, $runtime_params )
				? $runtime_params[ $name ]
				: $this->resolve_parameter( $param );
		}

		return $resolved;
	}

	/**
	 * Validates a constructor or factory signature and its runtime parameters.
	 *
	 * @param list<ReflectionParameter> $params          Constructor or factory parameters.
	 * @param array<string, mixed>      $runtime_params  Runtime parameters by name.
	 *
	 * @throws ContainerException
	 */
	protected function validate_parameters( array $params, array $runtime_params ): void {
		$known_params = [];
		foreach ( $params as $param ) {
			if ( $param->isVariadic() ) {
				throw new ContainerException(
					"Variadic parameter `{$param->getName()}` of `{$this->get_declared_in( $param )}` is not supported."
				);
			}

			$known_params[ $param->getName() ] = true;
		}

		$unknown_params = array_diff_key( $runtime_params, $known_params );
		if ( $unknown_params ) {
			$names = implode( '`, `', array_keys( $unknown_params ) );
			throw new ContainerException( "Unknown parameter(s): `$names`." );
		}
	}

	/**
	 * Finds a value for one argument: loads a class dependency from the container
	 * or uses the argument's default value. A required scalar value cannot be resolved automatically.
	 *
	 * @param ReflectionParameter $param
	 *
	 * @return mixed|object
	 *
	 * @throws ContainerException
	 * @throws NotFoundException
	 * @throws ReflectionException
	 */
	protected function resolve_parameter( ReflectionParameter $param ) {
		$dependency = $this->get_parameter_class( $param );
		if ( $dependency ) {
			return $this->get( $dependency );
		}

		if ( $param->isOptional() ) {
			return $param->getDefaultValue();
		}

		$message = "Parameter `{$param->getName()}` of `{$this->get_declared_in( $param )}` not resolved.";
		throw new ContainerException( $message );
	}

	/**
	 * @return class-string|null
	 */
	protected function get_parameter_class( ReflectionParameter $param ): ?string {
		$type = $param->getType();

		if ( $type instanceof ReflectionNamedType && ! $type->isBuiltin() ) {
			/** @var class-string */
			return $type->getName();
		}

		return null;
	}

	/**
	 * Checks whether a class and its constructor dependency graph can be autowired.
	 * Does not instantiate classes or invoke registered factories.
	 *
	 * @param class-string              $class
	 * @param array<class-string, true> $checking Classes currently being inspected.
	 */
	protected function can_resolve_class( string $class, array $checking = [] ): bool {
		if ( isset( $checking[ $class ] ) ) {
			return false;
		}

		$checking[ $class ] = true;

		$reflection = $this->get_reflection( $class );

		if ( ! $reflection->isInstantiable() ) {
			return false;
		}

		$constructor = $reflection->getConstructor();
		if ( ! $constructor ) {
			return true;
		}

		foreach ( $constructor->getParameters() as $param ) {
			if ( $param->isVariadic() ) {
				return false;
			}

			$id = $this->get_parameter_class( $param );
			if ( $id ) {
				if (
					! isset( $this->instances[ $id ] )
					&& ! isset( $this->definitions[ $id ] )
					&& ( ! class_exists( $id ) || ! $this->can_resolve_class( $id, $checking ) )
				) {
					return false;
				}

				continue;
			}

			if ( ! $param->isOptional() ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Starts resolving an entry to check circular dependencies.
	 *
	 * @param class-string $id
	 *
	 * @throws ContainerException
	 */
	protected function begin_resolving( string $id ): void {
		if ( isset( $this->resolving[ $id ] ) ) {
			$chain = implode( ' → ', array_keys( $this->resolving ) ) . " → $id";
			throw new ContainerException( "Circular dependency detected: $chain" );
		}

		$this->resolving[ $id ] = true;
	}

	protected function end_resolving( string $id ): void {
		unset( $this->resolving[ $id ] );
	}

	protected function is_valid_id( string $id ): bool {
		return class_exists( $id ) || interface_exists( $id );
	}

	/**
	 * @param class-string $class
	 * @return ReflectionClass<object>
	 *
	 * @throws ReflectionException
	 */
	protected function get_reflection( string $class ): ReflectionClass {
		return $this->reflection_cache[ $class ] ??= new ReflectionClass( $class );
	}

	protected function get_declared_in( ReflectionParameter $param ): string {
		return $param->getDeclaringClass()
			? $param->getDeclaringClass()->getName()
			: $param->getDeclaringFunction()->getName();
	}

}

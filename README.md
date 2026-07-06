![PHPUnit 100%](https://img.shields.io/badge/PHPUnit-100%25-green.svg)
![PHPStan level 9](https://img.shields.io/badge/PHPStan-level%209-green.svg)
![PHP 7.4 and 8.x](https://img.shields.io/badge/PHP-7.4%20%7C%208.x-777bb4.svg)
![Dependencies: none](https://img.shields.io/badge/dependencies-none-green.svg)
![License: MIT](https://img.shields.io/badge/license-MIT-blue.svg)

LiteWire DI Container
=====================

A tiny single-file autowire DI container for PHP and WordPress applications.


Table of contents
-----------------
* [Design goals](#design-goals)
* [Features](#features)
* [Basic usage](#basic-usage)
* [`has()`](#has)
* [`get()`](#get)
  * [Autowiring](#get--autowiring)
  * [Shared services](#get--shared-services)
  * [Scalar values](#get--scalar-values)
* [`set()`](#set)
  * [Register an existing object](#set--register-an-existing-object)
  * [Register an interface implementation](#set--register-an-interface-implementation)
  * [Register a factory](#set--register-a-factory)
  * [Factory autowiring](#set--factory-autowiring)
* [`make()`](#make)
  * [New instances](#make---new-instances)
  * [Runtime parameters](#make---runtime-parameters)
* [Benchmarks](#benchmarks)
* [Comparison with other containers](#comparison-with-other-containers)
* [Limitations](#limitations)
* [Inspired by](#inspired-by)


Design goals
------------
This container is intentionally small. It is designed for small projects, plugins, themes, and libraries where a full-featured container like Symfony DI or PHP-DI would be too much.

It is not trying to replace Symfony DI, PHP-DI, Laravel Container, or other full-featured dependency injection containers. It is useful when you need simple autowiring without configuration files, compiled cache, service providers, tags, scopes, scalar parameter storage, or framework integration.

The main idea is to provide a PSR-11-style API built around `get()` and `has()`:

```php
$container->get( Service::class );
$container->has( Service::class );
```

For larger projects, this makes migration to a bigger container easier.

The container does not implement `Psr\Container\ContainerInterface` and does not depend on `psr/container`.



Features
--------
* Single PHP file
* No dependencies
* Autowiring by constructor type hints
* Shared services via get()
* New instances via make()
* Factory closures
* Runtime constructor parameters for make()
* Reflection cache
* Object-first design
* Convenient for WordPress plugins and themes



Basic usage
-----------
`Logger` will be created automatically because it is declared as a constructor dependency.

```php
class Logger {
	public function log( string $message ): void {
		error_log( $message );
	}
}

class Service {
	public function __construct( 
		private Logger $logger
	) {}

	public function run(): void {
		$this->logger->log( 'Service started.' );
	}
}

$container = new Container();
$service = $container->get( Service::class );
$service->run();
```


### WordPress example

```php
$container = new Container();

// Register factory for Plugin class
$container->set( Plugin::class, function () {
	return new Plugin( __FILE__ );
} );

add_action( 'plugins_loaded', function () use ( $container ) {
	$container->get( Plugin::class )->init();
} );
```


has()
-----
Checks whether the service was registered, already resolved, or can be autowired. For an unregistered
class, its constructor and complete dependency graph must be resolvable by the container.

API:
```php
$container->has( class-string $id ): bool;
```

Usage:
```php
$container->has( Service::class ); // true if registered, resolved, or autowireable
$container->has( 'Unknown' );      // false
```


get()
-----
Gets a shared service instance – singleton. 

If the service was already created, the same object is returned.

API:
```php
$container->get( class-string $id );
```

### get() – Autowiring 
See basic usage above.


### get() – Shared services

`get()` resolves a service and stores it in the container. The next call returns the same instance.

```php
$a = $container->get( Some_Service::class );
$b = $container->get( Some_Service::class );

var_dump( $a === $b ); // true
```

### get() – Scalar values

Required scalar values cannot be resolved automatically – `ContainerException` will be thrown.

```php
final class Config {
	public function __construct(
		private string $path
	) {}
}

$container->get( Config::class ); // ContainerException
```

Use `make()` with named parameters:
```php
$config = $container->make( Config::class, [
	'path' => __DIR__ . '/config.php',
] );
```

Optional scalar values are supported:
```php
final class Config {
	public function __construct(
		private string $path = 'config.php'
	) {}
}

$config = $container->get( Config::class ); // no error
```



set()
-----
Registers a service definition.

API:
```php
$container->set( class-string $id, object|Closure|class-string $service ): void;
```

Service IDs must be existing class/interface name – regular string isn’t supported:

```php
$container->set( Logger_Interface::class, File_Logger::class ); // valid
$container->set( 'logger', File_Logger::class );                // InvalidArgumentException
```

Accepted values:

* existing object - `new MyClass()`
* existing class name - `MyClass::class`
* closure factory - `static fn () => new MyClass()`

> [!IMPORTANT]
> Configure the container before the first call to `get()`. Replacing a definition with `set()` removes the stored instance for that ID, but does not rebuild other shared services that were already created with the previous instance as a dependency.


### set() – Register an existing object
```php
$container->set( Logger::class, $logger );
$service = $container->get( Service::class );
```


### set() – Register an interface implementation
```php
$container->set( Logger_Interface::class, File_Logger::class );
$logger = $container->get( Logger_Interface::class );
```


### set() – Register a factory
Factories must return an object.

```php
$container->set( Client::class, static function () {
	return new Client( 'https://example.com' );
} );

$client = $container->get( Client::class );
```

### set() – Factory autowiring
Factory parameters are autowired for both `get()` and `make()`:

```php
$container->set( Mailer::class, static function ( Logger $logger ) {
	return new Mailer( $logger );
} );

$shared_mailer = $container->get( Mailer::class );
$fresh_mailer = $container->make( Mailer::class );
```

Type-hint `Container` in factory parameters to receive the container for factory:

```php
$container->set( Plugin::class, static function ( Container $container ) {
	return new Plugin( $container->get( Config::class ) );
} );

$plugin = $container->get( Plugin::class );
```


make()
------
Creates a fresh object each time from a registered definition or class name.

Unlike `get()`, it does not store (caches) the created object in the container.

API:
```php
$container->make( class-string $id, array $parameters = [] );
```

> [!NOTE]
> Definitions registered as existing object instances cannot be used with `make()` - use `get()` to retrieve those instances.
>
> Only the requested root object is created anew. Missing class dependencies are resolved through `get()`, so those dependencies are shared and reused by subsequent calls.
> 
> Class-string definitions are instantiated again, and closure factories are invoked on every call.
> 
> Factories must return an object but are responsible for whether that object is a new instance.


### make() - New instances
`make()` creates a new object and does not save it in the container.

```php
$a = $container->make( Some_Service::class );
$b = $container->make( Some_Service::class );

var_dump( $a === $b ); // false
```

This is useful for stateful objects, DTOs, handlers, commands, forms, and other short-lived objects.


### make() - Runtime parameters
The second argument of `make()` is an array of constructor parameters, keyed by parameter name. Values provided in this array are passed directly to the constructor. Any missing class dependencies are resolved automatically by the container.

If the array contains a parameter name that does not exist in the constructor, `make()` throws a `ContainerException`.

This allows `make()` to be used as a factory for objects that combine autowired services with runtime values. The factory call can then be replaced with a mock in tests.

```php
class Mailer {
	public function __construct(
		private Logger $logger,
		private string $from
	) {}
}

$mailer = $container->make( Mailer::class, [
	'from' => 'admin@example.com',
] );
```


Benchmarks
----------
Performance benchmarks cover direct instantiation, cold and stored `get()`, cold and reflection-cached `make()`, factory invocation, and both cold and stored deep autowiring.

Benchmark results depend on the machine and PHP version. Compare changes in the same environment rather than treating individual timings as universal limits.

Results for PHP 8.5.5 (with OPcache enabled):

| Subject                    | Runs × Rounds | Mem Peak |  Time (Variance) |
|----------------------------|--------------:|---------:|-----------------:|
| `direct_instantiation`     |    10 000 × 5 |  1.565mb | 0.078μs (±4.60%) |
| `cold_get`                 |    10 000 × 5 | 16.488mb | 1.946μs (±1.68%) |
| `stored_get`               |    10 000 × 5 |  1.565mb | 0.062μs (±6.05%) |
| `cold_reflection_make`     |    10 000 × 5 | 15.928mb | 1.918μs (±0.74%) |
| `cached_reflection_make`   |    10 000 × 5 |  1.565mb | 0.769μs (±0.45%) |
| `registered_factory_make`  |    10 000 × 5 |  1.565mb | 0.418μs (±1.73%) |
| `cold_deep_autowiring`     |    10 000 × 5 | 18.533mb | 2.744μs (±1.13%) |
| `stored_deep_autowiring`   |    10 000 × 5 |  1.565mb | 0.061μs (±1.07%) |

Legend:

* **Runs** — time benchmark method executed per round.
* **Rounds** — how many times the complete benchmark is repeated.
* **Time** — average execution time per run (1 μs = 0.001 ms).
* **Variance** — how much execution time differs between rounds.
* **Mem Peak** — peak memory usage of the entire benchmark process.

Subject:

* `direct_instantiation` — creates an object and its dependencies manually using `new`, without the container.
* `cold_get` — resolves a service for the first time.
* `stored_get` — returns a service already created and stored by `get()`.
* `cold_reflection_make` — creates a fresh object before reflection metadata has been cached.
* `cached_reflection_make` — creates a fresh object using cached reflection metadata.
* `registered_factory_make` — creates a fresh object using a registered closure factory.
* `cold_deep_autowiring` — resolves and creates a complete multi-level dependency graph for the first time.
* `stored_deep_autowiring` — returns the root service of an already resolved dependency graph.

Conclusions:

Unlike larger containers such as PHP-DI, LiteWire DI does not keep a compiled container between requests. According to this benchmark, it would save only about 0.115 ms for 100 objects or 1.15 ms for 1,000. For small applications, this is usually too little to justify compilation, cache files, and cache invalidation.

A compiled container may still help large applications with thousands of services. LiteWire DI instead favors simpler setup and predictable runtime behavior for smaller dependency graphs.

* Reflection caching makes `make()` about 2.5× faster.
* A registered factory is about 1.8× faster than cached reflection.
* Deep autowiring costs 2.744 μs initially, then 0.061 μs for stored results.



Comparison with other containers
---------------

| Container              | Deps |     PSR-11 | Autowiring |           Shared services |   New instance method | Scalars | Config |
|------------------------|-----:|-----------:|-----------:|--------------------------:|----------------------:|--------:|-------:|
| LiteWire DI            |   no |      style |        yes |                   `get()` |              `make()` | runtime |     no |
| PHP-DI                 |  yes |        yes |        yes |                   `get()` |              `make()` |     yes |    yes |
| Symfony DI             |  yes |        yes |        yes |                   `get()` |    factories/services |     yes |    yes |
| Laravel Container      |  yes | partly/yes |        yes | `singleton(), instance()` |              `make()` |     yes |    yes |
| Laminas ServiceManager |  yes |        yes |        yes |           shared services |             `build()` |     yes |    yes |
| League Container       |  yes |        yes |        yes |             `addShared()` | definitions/factories |     yes |    yes |
| Yii DI Container       |  yes |         no |        yes |          `setSingleton()` |               `get()` |     yes |    yes |
| Pimple                 |  yes |         no |         no |          default behavior |           `factory()` |     yes |    yes |
| Nette DI               |  yes | no/adapter |        yes |        generated services |   generated factories |     yes |    yes |

Best for:

* `LiteWire DI` – simple PHP apps, WP plugins
* `PHP-DI` – medium and large apps
* `Symfony DI` – Symfony apps, compiled container
* `Laravel Container` – Laravel apps
* `Laminas ServiceManager` – Laminas/Mezzio apps
* `Pimple` – small explicit service containers
* `League Container` – framework-agnostic DI
* `Yii DI Container` – Yii apps
* `Nette DI` – Nette apps


Limitations
-----------
* PSR-11-style API without real implementing the PSR-11 interfaces
* No compiled container
* No service providers
* No scopes
* No tags
* No scalar parameter storage
* No union/intersection type resolving
* No variadic parameter resolving
* Required scalar constructor parameters must be provided manually


Inspired by
-----------
Inspired by [Simple DIC](https://github.com/renakdup/simple-dic)

LiteWire DI keeps the same single-file, dependency-free approach, but uses a stricter, object-only service model:

1. Service IDs must be existing class or interface names. Arbitrary string keys are rejected.
1. The container stores only objects other values and arrays cannot be registered.
1. `make()` accepts named runtime parameters for constructors and factories.
1. `make()` respects registered class and factory definitions instead of resolving only the class passed as its ID.
1. `has()` reports existing concrete classes that can be autowired without prior registration.
1. Factory parameters are resolved in the same way as constructor parameters in both `get()` and `make()`. 
1. Generic PHPDoc preserves the concrete return type of `get()` and `make()` for IDEs and static analysis.
1. A factory may request the container, other services, default values, and runtime values.
1. Factory results are validated: returning a primitive, array, or `null` throws a `ContainerException`.
1. Circular dependencies are detected and reported with the resolution chain.
1. Invalid or unsupported definitions and parameters fail with explicit exceptions.

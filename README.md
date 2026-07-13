![PHPUnit 100%](https://img.shields.io/badge/PHPUnit-100%25-green.svg)
![PHPStan level 9](https://img.shields.io/badge/PHPStan-level%209-green.svg)
![PHP 7.4 and 8.x](https://img.shields.io/badge/PHP-7.4%20%7C%208.x-777bb4.svg)
![Dependencies: none](https://img.shields.io/badge/dependencies-none-green.svg)
![License: MIT](https://img.shields.io/badge/license-MIT-blue.svg)
[![Last CI](https://github.com/doiftrue/litewire-di/actions/workflows/ci.yml/badge.svg)](https://github.com/doiftrue/litewire-di/actions/workflows/ci.yml)

LiteWire DI Container
=====================

A tiny single-file autowire DI container for PHP and WordPress applications.

See: https://doiftrue.github.io/litewire-di/


Compatibility
-------------

LiteWire DI supports PHP 7.4 and every PHP 8 minor release from 8.0 through 8.5. Each supported version is tested by CI. Future PHP versions are added after compatibility has been verified.


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

- Keep the whole container in a single PHP file.
- Use no external dependencies.
- Register existing objects, classes, and closure factories with `set()`.
- Autowire registered and unregistered classes.
- Return shared service instances with `get()`.
- Create a new instance every time with `make()`.
- Pass named runtime parameters to `make()`.
- Check whether classes and interfaces can be resolved with `has()`.
- Use an object-first design with class and interface names as service IDs.
- Use default values for scalar constructor parameters.
- Use the modern Reflection API on PHP 8.
- Inject the container itself as a dependency.
- Cache Reflection data inside each container instance.
- Detect circular dependencies and show the full resolution chain.
- Include benchmarks, PHPDoc, and inline comments.
- Include Composer configuration for publishing the package on Packagist.
- Test the package with PHPUnit and PHPStan in CI.
- Use PHPStan at level 9.

Partially supported features:

- Services can receive configuration through factories, runtime parameters, or a registered configuration object, but the container does not have a separate configuration array. See [Passing configuration to services](docs/content/config-usage-example.md).
- Invokable objects can be wrapped in a closure, but objects with `__invoke()` are not treated as factories automatically.
- The container uses normal PHP code for configuration. It does not provide attributes or a special configuration language.
- CI generates a test coverage report for `Container.php`; the badge remains static.
- The package is ready for Packagist, but publishing it is a separate manual step.



Usage Guide
--------
LiteWire DI has four public methods:

- `has()` checks if a service can be loaded.
- `set()` tells the container how to create an object.
- `get()` returns a shared object (and create it if now exists).
- `make()` creates a new object.

API:
```php
$container->has( class-string $id ): bool;
$container->set( class-string $id, object|Closure|class-string $service ): void;
$container->get( class-string $id );
$container->make( class-string $id, array $parameters = [] );
```

All service IDs must be real class or interface names. Plain names such as `logger` are not supported.


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


WordPress example:

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

More examples:

- [Passing configuration to services](docs/content/config-usage-example.md)
- [Complex WordPress plugin example](DOC/wordpress-plugin.md)



has()
-----
Checks whether the service was registered, already resolved, or can be autowired.

For an unregistered class, its constructor and complete dependency graph must be valid.

Usage:
```php
$container->has( Service::class ); // true if registered, resolved, or autowireable
$container->has( 'Unknown' );      // false
```


get()
-----
Gets a shared service instance – singleton. 

If the service was already created, the same object is returned.

### get() – Autowiring 

`get()` can create an unregistered class by resolving the class dependencies declared in its constructor:

```php
class Logger {
	public function write( string $message ): void {
		error_log( $message );
	}
}

class Report_Service {
	private $logger;

	public function __construct( Logger $logger ) {
		$this->logger = $logger;
	}

	public function generate(): void {
		$this->logger->write( 'Report generated.' );
	}
}

$service = $container->get( Report_Service::class );
$service->generate();
```

Neither `Report_Service` nor `Logger` needs to be registered: the container inspects both constructors and builds the complete dependency graph automatically.


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

> [!NOTE]
> Definitions registered as existing object instances cannot be used with `make()` - use `get()` to retrieve those instances.

> [!NOTE]
> Only the requested root object is created anew. Missing class dependencies are resolved through `get()`, so those dependencies are shared and reused by subsequent calls.

> [!NOTE]
> Class-string definitions are instantiated again, and closure factories are invoked on every call.

> [!NOTE]
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

| Subject                      | Runs × Rounds |  Mem Peak |  Time (Variance) |
|------------------------------|--------------:|----------:|-----------------:|
| direct_instantiation         |    10 000 × 5 | 678.904kb | 0.078μs (±5.48%) |
| get__cold                    |    10 000 × 5 |  16.449mb | 2.027μs (±0.65%) |
| get__stored                  |    10 000 × 5 | 678.880kb | 0.058μs (±4.47%) |
| get__deep_autowiring__cold   |    10 000 × 5 |  18.494mb | 2.895μs (±0.32%) |
| get__deep_autowiring__stored |    10 000 × 5 | 666.744kb | 0.059μs (±4.00%) |
| make__reflection__cold       |    10 000 × 5 |  15.889mb | 2.016μs (±1.19%) |
| make__reflection__cached     |    10 000 × 5 | 678.904kb | 0.804μs (±3.53%) |
| make__registered_factory     |    10 000 × 5 | 678.904kb | 0.416μs (±6.95%) |

Legend:

- **Subject** — the operation measured by PHPBench.
- **Runs** — time benchmark method executed per round.
- **Rounds** — how many times the complete benchmark is repeated.
- **Time** — modal execution time per run (1 μs = 0.001 ms).
- **Variance** — how much execution time differs between rounds.
- **Mem Peak** — peak memory usage of the entire benchmark process.

Conclusions:

Unlike larger containers such as PHP-DI, LiteWire DI does not keep a compiled container between requests. According to this benchmark, it would save only about 0.121 ms for 100 objects or 1.21 ms for 1,000. For small applications, this is usually too little to justify compilation, cache files, and cache invalidation.

A compiled container may still help large applications with thousands of services. LiteWire DI instead favors simpler setup and predictable runtime behavior for smaller dependency graphs.

* Reflection caching makes `make()` about 2.5× faster.
* A registered factory is about 1.8× faster than cached reflection.
* Deep autowiring costs 2.744 μs initially, then 0.061 μs for stored results.

See: [Detailed benchmark results](benchmarks/README.md)


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
LiteWire DI intentionally does not include:

- A compiled container.
- Complex configuration files.
- Attributes or a special configuration language (DSL).
- A debug mode.
- Arbitrary string service IDs.
- Invokable objects used directly as factories.
- Service definitions passed through the container constructor.
- Service providers.
- Scopes.
- Tags.
- Scalar parameter storage.
- Union or intersection type resolution.
- Variadic parameter resolution.

Required scalar constructor parameters must be provided manually.

These features would make the public API larger and less focused. The main advantage of LiteWire DI is its strict object model in one small PHP file.

Full PSR-11 support is also a tradeoff because it requires a dependency on `psr/container`. LiteWire DI keeps a PSR-11-style API instead. A separate optional adapter may be added in the future.

The main next tasks are to make `has()` fully correct, publish real test coverage, and test every supported PHP version.


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

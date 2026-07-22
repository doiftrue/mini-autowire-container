![Dependencies: none](https://img.shields.io/badge/dependencies-none-green.svg)
![PHP 7.4 and 8.x](https://img.shields.io/badge/PHP-7.4%20%7C%208.x-777bb4.svg)
![License: MIT](https://img.shields.io/badge/license-MIT-blue.svg)
![PHPUnit 100%](https://img.shields.io/badge/PHPUnit-100%25-green.svg)
![PHPStan level 9](https://img.shields.io/badge/PHPStan-level%209-green.svg)
[![Tests & Analysis](https://img.shields.io/github/actions/workflow/status/doiftrue/litewire-di/ci.yml?label=Tests%20%26%20Analysis)](https://github.com/doiftrue/litewire-di/actions/workflows/ci.yml)

LiteWire DI Container
=====================

A tiny single-file autowiring DI container for PHP and WordPress.

Use it when Symfony DI, PHP-DI, or a framework container feels too heavy. You get a familiar `get()` / `has()` API, autowiring, factories, shared services, and no runtime dependencies.

Full documentation: https://doiftrue.github.io/litewire-di/


Installation
------------

Install with Composer:

```bash
composer require doiftrue/litewire-di
```

Or copy the single [`Container.php`](Container.php) file into your project. When copying it, change the `Kama\LiteWireDI` namespace to one owned by your project to avoid collisions with another copy of the container.



Compatibility
-------------

PHP 7.4 and PHP 8.0-8.5 are tested in CI.


Features
--------

- Single portable PHP file.
- No runtime dependencies.
- Register existing objects, classes, closure factories, and configured constructor parameters with `set()`.
- Autowire registered and unregistered classes.
- Return shared service instances with `get()`.
- Create fresh instances with `make()`.
- Pass named runtime parameters to `make()`.
- Check whether classes and interfaces can be resolved with `has()`.
- Detect circular dependencies and show the full resolution chain.
- Use an object-first design with class and interface names as service IDs.


API
---

Four public methods:

- `has()` checks if a service can be resolved.
- `set()` registers an object, class, factory, or named parameters for an instantiable class.
- `get()` returns a shared object.
- `make()` creates a fresh object.

```php
$container->has( class-string $id ): bool;
$container->set( class-string $id, object|Closure|class-string|array<string, mixed> $service ): void;
$container->get( class-string $id );
$container->make( class-string $id, array $parameters = [] );
```

Service IDs must be class or interface names. Plain strings like `logger` are not supported.


Examples
--------

### Quick start

```php
use Kama\LiteWireDI\Container;

final class Logger {
	public function log( string $message ): void {
		error_log( $message );
	}
}

final class Service {
	public function __construct(
		private readonly Logger $logger,
	) {}

	public function run(): void {
		$this->logger->log( 'Service started.' );
	}
}

$container = new Container();
$service = $container->get( Service::class );
$service->run(); // Logger is created automatically
```


### Interface binding

```php
$container->set( Logger_Interface::class, File_Logger::class );

$logger = $container->get( Logger_Interface::class );
```

### Configured parameters

Pass an associative array to register named constructor parameters for an instantiable class. Missing class dependencies are autowired. `get()` creates and stores the configured service.

```php
final class Plugin {
	public function __construct(
		private readonly string $main_file,
		private readonly Options $options,
	) {}
}

$container->set( Plugin::class, [
	'main_file' => __FILE__,
] );

$plugin = $container->get( Plugin::class );
```

Configured parameters cannot be registered directly for an interface because the array does not identify an implementation. Use a class binding when the implementation needs no scalar configuration; otherwise use a factory.

### Factory registration

Factories must return an object. Their parameters are autowired too.

```php
$container->set( Mailer::class, static function ( Logger $logger ) {
	return new Mailer( $logger );
} );

$shared_mailer = $container->get( Mailer::class );
$fresh_mailer = $container->make( Mailer::class );
```

### Runtime parameters

`make()` creates a fresh root object and accepts named constructor values.

```php
final class Mailer {
	public function __construct(
		private readonly Logger $logger,
		private readonly string $from,
	) {}
}

$mailer = $container->make( Mailer::class, [
	'from' => 'admin@example.com',
] );
```

When the class has configured parameters from `set()`, `make()` uses them as defaults. Parameters passed directly to `make()` take priority.


Documentation
-------------

- [Full documentation](https://doiftrue.github.io/litewire-di/)
- [Configuration guide](https://doiftrue.github.io/litewire-di/guide/configuration-and-factories)
- [WordPress plugin guide](https://doiftrue.github.io/litewire-di/guide/full-wordpress)
- [Detailed benchmark report](benchmarks/README.md)


Benchmarks
----------
Benchmarks cover direct `new`, `get()`, `make()`, factories, and deep autowiring.

Treat timings as local numbers, not universal limits.

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

- **Subject** - the operation measured by PHPBench.
- **Runs** - time benchmark method executed per round.
- **Rounds** - how many times the complete benchmark is repeated.
- **Time** - modal execution time per run (1 μs = 0.001 ms).
- **Variance** - how much execution time differs between rounds.
- **Mem Peak** - peak memory usage of the entire benchmark process.

Conclusions:

LiteWire DI does not compile a container between requests. In this benchmark, compilation would save about 0.121 ms for 100 objects or 1.21 ms for 1,000 objects.

That can matter for large apps. For small dependency graphs, LiteWire DI favors simple setup and predictable runtime behavior.

* Reflection caching makes `make()` about 2.5× faster.
* A registered factory is about 1.8× faster than cached reflection.
* Deep autowiring costs 2.744 μs initially, then 0.061 μs for stored results.

See: [Detailed benchmark results](benchmarks/README.md)


Limitations
-----------

LiteWire DI keeps the API small. There is no compiled container, service providers, scopes, tags, standalone scalar storage, string service IDs, or config format. Pass required scalar values as configured class parameters, factory arguments, `make()` parameters, or through a config object.

See the [full documentation](https://doiftrue.github.io/litewire-di/reference/limitations) for the detailed list.


Inspired by
-----------
Inspired by [Simple DIC](https://github.com/renakdup/simple-dic)

LiteWire DI keeps the same single-file, dependency-free approach, but uses a stricter, object-only service model:

1. Service IDs must be existing class or interface names. Arbitrary string keys are rejected.
1. Resolved container entries are always objects; associative arrays may only configure named parameters for an instantiable class.
1. `set()` accepts named constructor parameters for shared concrete services while autowiring the remaining dependencies.
1. `make()` accepts named runtime parameters for constructors and factories.
1. `make()` respects registered class and factory definitions instead of resolving only the class passed as its ID.
1. `has()` reports existing concrete classes that can be autowired without prior registration.
1. Factory parameters are resolved in the same way as constructor parameters in both `get()` and `make()`.
1. Generic PHPDoc preserves the concrete return type of `get()` and `make()` for IDEs and static analysis.
1. A factory may request the container, other services, default values, and runtime values.
1. Factory results are validated: returning a primitive, array, or `null` throws a `ContainerException`.
1. Circular dependencies are detected and reported with the resolution chain.
1. Invalid or unsupported definitions and parameters fail with explicit exceptions.

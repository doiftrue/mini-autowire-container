# LiteWire DI Container

A tiny single-file autowiring DI container for PHP and WordPress.


Installation
------------

Install with Composer:

```bash
composer require doiftrue/litewire-di
```

Or copy the single [`Container.php`](https://github.com/doiftrue/litewire-di/blob/main/Container.php) file into your project. When copying it, change the `Kama\LiteWireDI` namespace to one owned by your project to avoid collisions with another copy of the container.



Compatibility
-------------

PHP 7.4 and PHP 8.0-8.5 are tested in CI.


Design goals
------------
LiteWire DI is built for small projects, plugins, themes, and libraries where Symfony DI or PHP-DI would be too much.

It is not a replacement for large framework containers. It is for simple autowiring without config files, compiled cache, service providers, tags, scopes, scalar storage, or framework integration.

The API follows the familiar `get()` / `has()` shape:

```php
$container->get( Service::class );
$container->has( Service::class );
```

That keeps migration to a larger container straightforward.

It does not implement `Psr\Container\ContainerInterface` and does not require `psr/container`.


Features
--------

- Keep the whole container in a single PHP file.
- Use no external dependencies.
- Register existing objects, classes, closure factories, and configured constructor parameters with `set()`.
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

Tradeoffs:

- Configuration goes through parameters attached to concrete class definitions, factories, runtime parameters, or config objects. There is no standalone scalar storage.
- Invokable objects can be wrapped in closures, but are not factories automatically.
- Configuration is normal PHP code. There are no attributes or DSL.


Usage Guide
-----------
Four public methods:

- `has()` checks if a service can be resolved.
- `set()` registers an object, class, factory, or named parameters for an instantiable class.
- `get()` returns a shared object.
- `make()` creates a fresh object.

API:
```php
$container->has( class-string $id ): bool;
$container->set( class-string $id, object|Closure|class-string|array<string, mixed> $service ): void;
$container->get( class-string $id );
$container->make( class-string $id, array $parameters = [] );
```

Service IDs must be class or interface names. Plain strings like `logger` are not supported.


### Usage Example

`Logger` is created automatically from the constructor type.

```php
class Logger {
	public function log( string $message ): void {
		error_log( $message );
	}
}

class Service {
	public function __construct(
		private readonly Logger $logger,
	) {}

	public function run(): void {
		$this->logger->log( 'Service started.' );
	}
}

$container = new Container();
$service = $container->get( Service::class );
$service->run();
```



has()
-----
Checks whether a service is registered, already resolved, or autowireable.

For an unregistered class, the full constructor graph must be valid.

Usage:
```php
$container->has( Service::class ); // true if registered, resolved, or autowireable
$container->has( 'Unknown' );      // false
```


get()
-----
Returns a shared service instance.

If it was already created, the same object is returned.

### get() - Autowiring

`get()` can create an unregistered class from constructor types:

```php
class Logger {
	public function write( string $message ): void {
		error_log( $message );
	}
}

class Report_Service {
	public function __construct(
		private readonly Logger $logger,
	) {}

	public function generate(): void {
		$this->logger->write( 'Report generated.' );
	}
}

$service = $container->get( Report_Service::class );
$service->generate();
```

Neither `Report_Service` nor `Logger` needs registration. The container builds the graph automatically.


### get() - Shared services

`get()` stores the resolved service. The next call returns the same instance.

```php
$a = $container->get( Some_Service::class );
$b = $container->get( Some_Service::class );

var_dump( $a === $b ); // true
```

### get() - Scalar values

Required scalar values cannot be autowired. They throw `ContainerException`.

```php
final class Config {
	public function __construct(
		private readonly string $path,
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
		private readonly string $path = 'config.php',
	) {}
}

$config = $container->get( Config::class ); // no error
```


set()
-----
Registers a service definition.

Service IDs must be class or interface names. Regular strings are not supported:

```php
$container->set( Logger_Interface::class, File_Logger::class ); // valid
$container->set( 'logger', File_Logger::class );                // InvalidArgumentException
```

Accepted values:

* existing object - `new MyClass()`
* existing class name - `MyClass::class`
* closure factory - `static fn () => new MyClass()`
* named parameters for an instantiable class - `[ 'parameter_name' => $value ]`

> [!IMPORTANT]
> Configure the container before the first `get()`. Replacing a definition removes the stored instance for that ID, but already-created services are not rebuilt.


### set() - Register an existing object
```php
$container->set( Logger::class, $logger );
$service = $container->get( Service::class );
```


### set() - Register an interface implementation
```php
$container->set( Logger_Interface::class, File_Logger::class );
$logger = $container->get( Logger_Interface::class );
```


### set() - Configure constructor parameters

An associative array registers persistent named constructor parameters for the instantiable class used as the service ID. The container autowires any omitted class dependencies.

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

The configured object is shared like any other result returned by `get()`. Parameter names are checked when the service is resolved; unknown names throw `ContainerException`.

This form only works when the service ID is an instantiable class. For an interface, use a class-string binding when the implementation needs no scalar configuration; otherwise use a factory.


### set() - Register a factory
Factories must return an object.

```php
$container->set( Client::class, static function () {
	return new Client( 'https://example.com' );
} );

$client = $container->get( Client::class );
```

### set() - Factory autowiring
Factory parameters are autowired for both `get()` and `make()`:

```php
$container->set( Mailer::class, static function ( Logger $logger ) {
	return new Mailer( $logger );
} );

$shared_mailer = $container->get( Mailer::class );
$fresh_mailer = $container->make( Mailer::class );
```

Type-hint `Container` in a factory parameter to receive the container:

```php
$container->set( Plugin::class, static function ( Container $container ) {
	return new Plugin( $container->get( Config::class ) );
} );

$plugin = $container->get( Plugin::class );
```


make()
------
Creates a fresh object from a registered definition or class name.

Unlike `get()`, it does not store the object.


### make() - New instances
`make()` creates a new object without saving it.

```php
$a = $container->make( Some_Service::class );
$b = $container->make( Some_Service::class );

var_dump( $a === $b ); // false
```

This is useful for stateful objects, DTOs, handlers, commands, forms, and other short-lived objects.


### make() - Runtime parameters
The second argument of `make()` is an array keyed by constructor parameter name. Provided values go straight to the constructor. Missing class dependencies are autowired.

Unknown parameter names throw `ContainerException`.

This makes `make()` useful for objects that mix services with runtime values. In tests, the factory call can be replaced with a mock.

```php
class Mailer {
	public function __construct(
		private readonly Logger $logger,
		private readonly string $from,
	) {}
}

$mailer = $container->make( Mailer::class, [
	'from' => 'admin@example.com',
] );
```

If `set()` registered configured parameters for the class, `make()` uses them as defaults and creates a fresh instance. Parameters passed directly to `make()` take priority:

```php
$container->set( Mailer::class, [
	'from' => 'default@example.com',
] );

$mailer = $container->make( Mailer::class, [
	'from' => 'override@example.com',
] );
```


### make() - Existing object definitions
Definitions registered as existing object instances cannot be used with `make()`. Use `get()` to retrieve those instances.

```php
$logger = new Logger();

$container->set( Logger::class, $logger );

$same_logger = $container->get( Logger::class ); // OK.
$new_logger = $container->make( Logger::class ); // Throws ContainerException.
```


### make() - Shared dependencies
Only the requested root object is created anew. Class dependencies are resolved with `get()`, so they are shared and reused by subsequent calls.

```php
class ReportController {
	public function __construct(
		public readonly Logger $logger,
	) {}
}

$first = $container->make( ReportController::class );
$second = $container->make( ReportController::class );

var_dump( $first === $second ); // false
var_dump( $first->logger === $second->logger ); // true
```


### make() - Registered definitions
Class-string and configured-parameter definitions are instantiated again, and closure factories are invoked on every call.

```php
$container->set( Mailer::class, static fn () => new Mailer() );

var_dump( $container->make( Mailer::class ) === $container->make( Mailer::class ) ); // false
var_dump( $container->get( Mailer::class ) === $container->get( Mailer::class ) ); // true
```

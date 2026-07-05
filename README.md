# Mini Autowire Container

A tiny single-file autowire DI container for PHP and WordPress applications.


Design goals
------------
This container is intentionally small. It is designed for small projects, plugins, themes, and libraries where a full-featured container like Symfony DI or PHP-DI would be too much.

It is not trying to replace Symfony DI, PHP-DI, Laravel Container, or other full-featured dependency injection containers. It is useful when you need simple autowiring without configuration files, compiled cache, service providers, tags, scopes, scalar parameter storage, or framework integration.

The main idea is to keep application code close to common DI container concepts (PSR-11):

```php
$container->get( Service::class );
$container->has( Service::class );
```

For larger projects, this makes migration to a bigger container easier.


### Container API
```php
$container->set( string $id, object|Closure|string $service ): void;
$container->has( string $id ): bool;
$container->get( string $id );
$container->make( string $id, array $parameters = [] );
```





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


get()
-----
Gets a shared service instance – singleton. 

If the service was already created, the same object is returned.

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

Required scalar values cannot be resolved automatically – RuntimeException will be thrown.

```php
final class Config {
	public function __construct(
		private string $path
	) {}
}

$container->get( Config::class ); // RuntimeException
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


has()
-----
Checks whether the service was registered or already resolved.

```php
$container->has( Service::class ); // true if registered or resolved
$container->has( 'Unknown' );      // false
```


set()
-----
Registers a service definition.

Accepted values:

* existing object - `new MyClass()`
* class name - `MyClass::class`
* closure factory - `static function () { return new MyClass(); }`


### set() – Register an existing object

`$logger` is an existing instance of `Logger`.
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
$container->set( Client::class, static function (): Client {
	return new Client( 'https://example.com' );
} );

$client = $container->get( Client::class );
```


make()
------
Creates a fresh object from a registered definition or class name. 

Unlike `get()`, it does not store the created object in the container.

> [!NOTE]
> Definitions registered as existing object instances cannot be used with `make()`;
use `get()` to retrieve those instances.
> 
> Class-string definitions are instantiated again and closure factories are invoked on every call.
> 
> Factories must return an object, but are responsible for whether that object is a new instance.

### make() - New instances
`make()` creates a new object and does not save it in the container.

```php
$a = $container->make( Some_Service::class );
$b = $container->make( Some_Service::class );

var_dump( $a === $b ); // false
```

This is useful for stateful objects, DTOs, handlers, commands, forms, and other short-lived objects.

### make() - Runtime parameters
Named runtime parameters can be passed to `make()`.

Class dependencies are still autowired automatically. Scalar values must be passed manually or have default values.

Such a call may also be treated as a factory that can be mocked in tests.

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


Comparison with other containers
---------------

| Container              | Deps |     PSR-11 | Autowiring |           Shared services |   New instance method | Scalars | Config |
|------------------------|-----:|-----------:|-----------:|--------------------------:|----------------------:|--------:|-------:|
| Mini Container         |   no | compatible |        yes |                   `get()` |              `make()` | runtime |     no |
| PHP-DI                 |  yes |        yes |        yes |                   `get()` |              `make()` |     yes |    yes |
| Symfony DI             |  yes |        yes |        yes |                   `get()` |    factories/services |     yes |    yes |
| Laravel Container      |  yes | partly/yes |        yes | `singleton(), instance()` |              `make()` |     yes |    yes |
| Laminas ServiceManager |  yes |        yes |        yes |           shared services |             `build()` |     yes |    yes |
| Pimple                 |  yes |         no |         no |          default behavior |           `factory()` |     yes |    yes |
| League Container       |  yes |        yes |        yes |             `addShared()` | definitions/factories |     yes |    yes |
| Yii DI Container       |  yes |         no |        yes |          `setSingleton()` |               `get()` |     yes |    yes |
| Nette DI               |  yes | no/adapter |        yes |        generated services |   generated factories |     yes |    yes |

Best for:

* `Mini Container` – simple PHP apps, WP plugins
* `SimpleDic` – simple PHP apps, WP plugins
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

* No PSR-11 interface dependency included
* No compiled container
* No service providers
* No scopes
* No tags
* No scalar parameter storage
* No union/intersection type resolving
* Required scalar constructor parameters must be provided manually



> [!NOTE]
> Inspired by [Simple DIC](https://github.com/renakdup/simple-dic)
>
> This container differs by:
> 1. `set()` accepts only objects or class-strings (no primitives)
> 2. `make()` — Supports runtime parameters
> 3. Factory in `make()` — closure parameters are autowired (not just `$container`)
> 4. Factory in `get()` — closure receives `$this` (the container)

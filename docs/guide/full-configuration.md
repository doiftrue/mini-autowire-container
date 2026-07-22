# Configuration Usage Example

Configuration is a set of values that changes how an application works without changing its code.

Configuration helps to:

- keep changeable settings separate from application logic;
- use different values in production, development, and tests;
- avoid global variables and direct `getenv()` calls inside services.

Each container handles configuration differently. The examples below show the usual approach for each container.

All examples use PHP 8.2 syntax.


Service used in all examples
---------------------
Most examples below create this service:

```php
final class SomeService {
	public function __construct(
		private readonly string $db_host,
		private readonly int $db_port,
		private readonly string $db_name,
		private readonly string $api_url,
		private readonly int $api_timeout,
		private readonly string $cache_dir,
		private readonly bool $debug,
	) {}
}
```


LiteWire DI
-----------
LiteWire DI can only store objects, not individual config values. That keeps the container small, but configuration remains application code.

There are several reasonable ways to connect configuration values to services.

### Option 1: inject one config object

This is usually the simplest LiteWire DI option. The application creates one readonly configuration object, registers it in the container, and lets autowiring pass it to services.

file: `config.php`
```php
final readonly class AppConfig {
	public function __construct(
		public string $db_host,
		public int $db_port,
		public string $db_name,
		public string $api_url,
		public int $api_timeout,
		public string $cache_dir,
		public bool $debug,
	) {}
}

return new AppConfig(
	db_host: 'localhost',
	db_port: 3306,
	db_name: 'my_application',
	api_url: 'https://api.example.com',
	api_timeout: 10,
	cache_dir: __DIR__ . '/var/cache',
	debug: true,
);
```

The service asks for the config object instead of many scalar values:

```php
final class SomeService {
	public function __construct(
		private readonly AppConfig $config,
	) {}
}
```

The bootstrap file registers the configuration object:

file: `bootstrap.php`
```php
$container = new Container();
$config = require __DIR__ . '/config.php';

$container->set( AppConfig::class, $config );

$some_service = $container->get( SomeService::class );
```

Pros:

- smallest bootstrap code;
- no factory is needed for `SomeService`;
- the config object is typed and readonly.

Cons:

- `SomeService` depends on the whole `AppConfig` object;
- a large `AppConfig` can become a bag of unrelated settings.

> [!INFO]
> `AppConfig` contains values but no application logic. It is `readonly`, so services cannot accidentally change the settings after startup. The class and its values are both visible in one file.

### Option 2: split config into focused objects

If one config object grows too large, split it by responsibility:

```php
final readonly class DatabaseConfig {
	public function __construct(
		public string $host,
		public int $port,
		public string $name,
	) {}
}

final readonly class ApiConfig {
	public function __construct(
		public string $url,
		public int $timeout,
	) {}
}

final class SomeService {
	public function __construct(
		private readonly DatabaseConfig $database,
		private readonly ApiConfig $api,
	) {}
}
```

Register each configured object:

```php
$container = new Container();

$container->set( DatabaseConfig::class, new DatabaseConfig(
	host: 'localhost',
	port: 3306,
	name: 'my_application',
) );

$container->set( ApiConfig::class, new ApiConfig(
	url: 'https://api.example.com',
	timeout: 10,
) );

$some_service = $container->get( SomeService::class );
```

Pros:

- services depend only on the configuration they use;
- each config object has a clear purpose;
- large applications stay easier to navigate.

Cons:

- more classes and registrations;
- too many tiny config objects can add noise in a small application.

### Option 3: configure named constructor parameters

For an instantiable service class with scalar constructor arguments, `config.php` can return an associative parameter array that is passed directly to `set()`. The keys must match constructor parameter names. Any omitted class dependencies are autowired.

file: `config.php`
```php
return [
	'db_host' => 'localhost',
	'db_port' => 3306,
	'db_name' => 'my_application',
	'api_url' => 'https://api.example.com',
	'api_timeout' => 10,
	'cache_dir' => __DIR__ . '/var/cache',
	'debug' => true,
];
```

file: `bootstrap.php`
```php
$container = new Container();
$values = require __DIR__ . '/config.php';

$container->set( SomeService::class, $values );

$some_service = $container->get( SomeService::class );
```

Pros:

- the config file stays familiar while bootstrap code remains small;
- no extra config class or factory is required;
- remaining object dependencies are still autowired;
- `make()` can create a fresh instance and override individual configured values.

Cons:

- parameter names and value types are checked at runtime;
- the config file is coupled to this class's constructor signature;
- values are local to this class definition and are not reusable scalar entries;
- an interface whose implementation needs scalar configuration requires a factory because the array does not identify that implementation.

### Option 4: create a typed config object from an array

If the application already has an array config file but services should depend on a typed configuration object, map the loaded values into that object in bootstrap code.

file: `bootstrap.php`
```php
$container = new Container();
$values = require __DIR__ . '/config.php';

$container->set( AppConfig::class, new AppConfig(
	db_host: $values['db_host'],
	db_port: $values['db_port'],
	db_name: $values['db_name'],
	api_url: $values['api_url'],
	api_timeout: $values['api_timeout'],
	cache_dir: $values['cache_dir'],
	debug: $values['debug'],
) );

$some_service = $container->get( SomeService::class );
```

Pros:

- `config.php` is familiar and easy to override;
- values can be loaded, merged, or validated before objects are created;
- services receive a reusable typed config object.

Cons:

- array keys are not checked by PHP;
- bootstrap code has to map array values into the object;
- type errors are discovered later than with direct object creation.

### Option 5: use a factory for scalar constructor values

If a service should keep scalar constructor arguments, register a factory for that service:

```php
$container = new Container();

$container->set( SomeService::class, static function () {
	return new SomeService(
		db_host: 'localhost',
		db_port: 3306,
		db_name: 'my_application',
		api_url: 'https://api.example.com',
		api_timeout: 10,
		cache_dir: __DIR__ . '/var/cache',
		debug: true,
	);
} );

$some_service = $container->get( SomeService::class );
```

Pros:

- the service constructor stays explicit;
- no extra config class is required;
- useful for one-off services or third-party classes.

Cons:

- repeated scalar values can spread across factories;
- configuration is less reusable in tests and other services;
- large factories become harder to read.

For small applications, option 1 is usually enough. If the config object becomes too broad, option 2 is the cleaner next step. Option 3 is the most concise choice when an array config belongs to one concrete class. Use option 4 when services should receive a reusable typed config object, and option 5 when construction needs custom logic or an interface implementation must be selected at the same time.


PHP-DI
------
PHP-DI can store values under string IDs such as `database.host` and `app.debug`. It can pass those values directly to the service constructor.

The PHP-DI definition file contains both the values and instructions that connect each value to a constructor argument:

file: `config.php`
```php
use function DI\autowire;
use function DI\get;

return [
	'database.host' => 'localhost',
	'database.port' => 3306,
	'database.name' => 'my_application',
	'api.url' => 'https://api.example.com',
	'api.timeout' => 10,
	'cache.directory' => __DIR__ . '/var/cache',
	'app.debug' => true,

	SomeService::class => autowire()
		->constructorParameter( 'db_host', get( 'database.host' ) )
		->constructorParameter( 'db_port', get( 'database.port' ) )
		->constructorParameter( 'db_name', get( 'database.name' ) )
		->constructorParameter( 'api_url', get( 'api.url' ) )
		->constructorParameter( 'api_timeout', get( 'api.timeout' ) )
		->constructorParameter( 'cache_dir', get( 'cache.directory' ) )
		->constructorParameter( 'debug', get( 'app.debug' ) ),
];
```

file: `bootstrap.php`
```php
$builder = new DI\ContainerBuilder();
$builder->addDefinitions( __DIR__ . '/config.php' );
$container = $builder->build();

$some_service = $container->get( SomeService::class );
```

PHP-DI resolves every `get()` reference and passes the corresponding value to `SomeService`. The container owns both the scalar values and the rules for injecting them.


Symfony DI
----------
Symfony stores scalar values as container parameters. A service can reference those parameters in `services.yaml`:

file: `config/services.yaml`
```yaml
parameters:
    database.host: 'localhost'
    database.port: 3306
    database.name: 'my_application'
    api.url: 'https://api.example.com'
    api.timeout: 10
    cache.directory: '%kernel.project_dir%/var/cache'
    app.debug: true

services:
    SomeService:
        public: true
        arguments:
            $db_host: '%database.host%'
            $db_port: '%database.port%'
            $db_name: '%database.name%'
            $api_url: '%api.url%'
            $api_timeout: '%api.timeout%'
            $cache_dir: '%cache.directory%'
            $debug: '%app.debug%'
```

file: `bootstrap.php`
```php
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;

$container = new ContainerBuilder();
$container->setParameter( 'kernel.project_dir', __DIR__ );

$loader = new YamlFileLoader(
	$container,
	new FileLocator( __DIR__ . '/config' )
);
$loader->load( 'services.yaml' );

$container->compile();

$some_service = $container->get( SomeService::class );
```

> [!INFO]
> The bootstrap code creates the container, loads `services.yaml`, and compiles all definitions.
> 
> Symfony resolves every `%parameter%` reference while compiling the container.
> 
> `public: true` is used only so this small example can call `get()` directly. Application services are normally private and injected into other services.


Laravel Container
-----------------
Laravel stores application settings in files inside the `config` directory:

file: `config/some_service.php`
```php
return [
	'db_host' => 'localhost',
	'db_port' => 3306,
	'db_name' => 'my_application',
	'api_url' => 'https://api.example.com',
	'api_timeout' => 10,
	'cache_dir' => storage_path( 'framework/cache' ),
	'debug' => true,
];
```

A service provider uses contextual binding to connect each constructor argument to a configuration value:

file: `app/Providers/SomeServiceProvider.php`
```php
use Illuminate\Support\ServiceProvider;

final class SomeServiceProvider extends ServiceProvider {
	public function register(): void {
		$this->app->when( SomeService::class )
			->needs( '$db_host' )
			->giveConfig( 'some_service.db_host' );

		$this->app->when( SomeService::class )
			->needs( '$db_port' )
			->giveConfig( 'some_service.db_port' );

		$this->app->when( SomeService::class )
			->needs( '$db_name' )
			->giveConfig( 'some_service.db_name' );

		$this->app->when( SomeService::class )
			->needs( '$api_url' )
			->giveConfig( 'some_service.api_url' );

		$this->app->when( SomeService::class )
			->needs( '$api_timeout' )
			->giveConfig( 'some_service.api_timeout' );

		$this->app->when( SomeService::class )
			->needs( '$cache_dir' )
			->giveConfig( 'some_service.cache_dir' );

		$this->app->when( SomeService::class )
			->needs( '$debug' )
			->giveConfig( 'some_service.debug' );
	}
}
```

The bootstrap code creates the application container, loads the configuration, and registers the provider:

file: `bootstrap.php`
```php
use Illuminate\Config\Repository;
use Illuminate\Foundation\Application;

$app = new Application( __DIR__ );

$app->instance( 'config', new Repository( [
	'some_service' => require __DIR__ . '/config/some_service.php',
] ) );

$app->register( SomeServiceProvider::class );

$some_service = $app->make( SomeService::class );
```

`$app->register()` creates the provider and calls its `register()` method. Laravel then reads the configured values and injects them when `$app->make()` resolves `SomeService`.



Are these approaches equivalent?
---------------------

They create the same `SomeService` with the same settings. PHP-DI can store individual values and inject them by reference. LiteWire DI either stores typed configuration objects or attaches literal named values directly to one concrete class definition.

The container features are also not equivalent. PHP-DI stores scalar entries and can reference, combine, and override definitions. LiteWire DI does not expose scalar entries: its configured parameter values belong only to their class definition. Loading, combining, or validating the original values remains application code.

For a small application, one `AppConfig` object is often enough. If it becomes too large, split it into focused objects such as `DatabaseConfig`, `ApiConfig`, and `CacheConfig` and register each object in the same way.

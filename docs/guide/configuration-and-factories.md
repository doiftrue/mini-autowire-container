# Configuring services

Most concrete classes need no configuration. If their constructor contains only concrete class types, LiteWire DI autowires them automatically.

Use `set()` only when the container cannot determine a value by itself:

| Situation | What to register |
| --- | --- |
| An interface needs an implementation | A class binding |
| A constructor needs a scalar value | Named parameters |
| Creation needs custom logic | A closure factory |
| The object already exists | The object itself |

## Bind an interface

The container cannot guess which class implements an interface. Choose it once during application startup:

```php
interface Logger {
	public function log( string $message ): void;
}

final class ErrorLogLogger implements Logger {
	public function log( string $message ): void {
		error_log( $message );
	}
}

$container->set( Logger::class, ErrorLogLogger::class );
```

Classes can now request `Logger` in their constructor, and the container will inject `ErrorLogLogger`.

## Provide a scalar value

Class dependencies are autowired, but values such as paths, URLs, and API keys must come from your application. Register them by constructor parameter name:

```php
final class ReportWriter {
	public function __construct(
		private readonly Logger $logger,
		private readonly string $outputDirectory,
	) {}
}

$container->set( ReportWriter::class, [
	'outputDirectory' => __DIR__ . '/reports',
] );

$writer = $container->get( ReportWriter::class );
```

`Logger` is still autowired from the interface binding. Only the value the container cannot know is supplied explicitly.

## Use a factory for custom creation

Use a closure when selecting an implementation also requires scalar values or custom setup. This is an alternative to the `ErrorLogLogger` binding above; choose one during startup before resolving `ReportWriter`:

```php
final class FileLogger implements Logger {
	public function __construct(
		private readonly string $file,
	) {}

	public function log( string $message ): void {
		file_put_contents( $this->file, $message . PHP_EOL, FILE_APPEND );
	}
}

$container->set( Logger::class, static function () {
	return new FileLogger( __DIR__ . '/logs/application.log' );
} );
```

The factory must return an object. Its class-typed parameters are autowired, so it can request other services when needed.

Configure services before the first `get()`. Replacing a definition later does not rebuild objects that have already received the old dependency.

---

::: info More configuration approaches
See the [configuration cookbook](/guide/full-configuration) for configuration objects, reusable settings, array mapping, factories, and comparisons with other containers.
:::

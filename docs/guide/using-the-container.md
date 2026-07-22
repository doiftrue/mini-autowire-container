# Using the container

Service IDs are existing class or interface names. Arbitrary string IDs such as `logger` are not supported.

## `get()` — resolve a shared service

`get()` creates a service once and returns the same object on later calls.

```php
$first = $container->get( ReportService::class );
$second = $container->get( ReportService::class );

var_dump( $first === $second ); // true
```

Use it for application services that should be shared for the lifetime of this container.

## `make()` — create a fresh service

`make()` resolves the class or its registered definition but never stores the root object.

```php
$preview = $container->make( ReportService::class );
$anotherPreview = $container->make( ReportService::class );

var_dump( $preview === $anotherPreview ); // false
```

Pass named runtime values when a constructor needs a scalar value:

```php
$mailer = $container->make( Mailer::class, [
	'from' => 'admin@example.test',
] );
```

Runtime parameters override configured parameters for that call.

## `has()` — check resolvability

`has()` returns `true` for a registered service, a previously created service, or an existing concrete class whose entire constructor graph can be resolved.

```php
if ( $container->has( ReportService::class ) ) {
	$report = $container->get( ReportService::class );
}
```

It returns `false` for unknown names and classes that need an unresolved dependency or required scalar value.

## `set()` — register a definition

Register interface bindings, existing objects, class aliases, closure factories, or named constructor parameters before first resolving the affected service.

```php
$container->set( LoggerInterface::class, FileLogger::class );
```

Replacing a definition removes the stored object for that ID. It does not rebuild services that were already created and received the old object. See [configuration and factories](/guide/configuration-and-factories) for each registration form.

---

::: info Full documentation
For every supported behavior, edge case, exception, and complete example, continue to the [container guide](/guide/full-documentation).
:::

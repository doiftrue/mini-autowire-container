# Limitations

LiteWire DI does not include:

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

Required scalar constructor parameters must be provided by your code through configured class parameters, a factory, `make()`, or a configuration object.

These features would make the API larger. LiteWire DI keeps one strict object model in one small PHP file.

Full PSR-11 support would require `psr/container`. LiteWire DI keeps the API style without the dependency. An optional adapter may be added later.

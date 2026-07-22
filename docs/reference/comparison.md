# Comparison with other containers

| Container              | Deps |     PSR-11 | Autowiring |           Shared services |   New instance method | Scalars | Config |
|------------------------|-----:|-----------:|-----------:|--------------------------:|----------------------:|--------:|-------:|
| LiteWire DI            |   no |      style |        yes |                   `get()` |              `make()` | definition/runtime |     no |
| PHP-DI                 |  yes |        yes |        yes |                   `get()` |              `make()` |     yes |    yes |
| Symfony DI             |  yes |        yes |        yes |                   `get()` |    factories/services |     yes |    yes |
| Laravel Container      |  yes | partly/yes |        yes | `singleton(), instance()` |              `make()` |     yes |    yes |
| Laminas ServiceManager |  yes |        yes |        yes |           shared services |             `build()` |     yes |    yes |
| League Container       |  yes |        yes |        yes |             `addShared()` | definitions/factories |     yes |    yes |
| Yii DI Container       |  yes |         no |        yes |          `setSingleton()` |               `get()` |     yes |    yes |
| Pimple                 |  yes |         no |         no |          default behavior |           `factory()` |     yes |    yes |
| Nette DI               |  yes | no/adapter |        yes |        generated services |   generated factories |     yes |    yes |

Best for:

- `LiteWire DI` - simple PHP apps, WP plugins
- `PHP-DI` - medium and large apps
- `Symfony DI` - Symfony apps, compiled container
- `Laravel Container` - Laravel apps
- `Laminas ServiceManager` - Laminas/Mezzio apps
- `Pimple` - small explicit service containers
- `League Container` - framework-agnostic DI
- `Yii DI Container` - Yii apps
- `Nette DI` - Nette apps

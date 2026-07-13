# Using LiteWire DI in a WordPress plugin

This example shows a small plugin with an admin settings page and an admin notice. It uses a container to connect the plugin classes.

The example is intentionally simple. It works as a useful starting structure, not as a complete production plugin.

## Why use a container in a plugin?

A WordPress plugin often starts with functions in one file. Later it gets admin pages, REST routes, repositories, API clients, and background jobs. Creating every object by hand then becomes repetitive.

The container helps with this object setup:

- constructors clearly show what each class needs;
- concrete classes are connected automatically;
- interface choices and scalar configuration stay in one bootstrap file;
- shared objects are reused;
- tests can create a class with fake dependencies without loading WordPress.

The container should be used during plugin startup. Do not pass it into every class. Normal classes should ask for their real dependencies in the constructor.

## Project structure

```text
my-plugin/
├── lib/
│   └── Container.php
├── autoload.php
├── my-plugin.php
└── src/
    ├── AdminNotice.php
    ├── Plugin.php
    ├── PluginConfig.php
    ├── SettingsPage.php
    └── Logger/
        ├── Logger.php
        └── WordPressLogger.php
```

## 1. Copy the container

Create the `lib` directory and copy the LiteWire DI [`Container.php`](../Container.php) file into it:

```text
my-plugin/lib/Container.php
```

The plugin now owns its copy of the container. Users do not need to install LiteWire DI or run Composer on their WordPress site.

When you update LiteWire DI, replace this file with the new version and test the plugin. Keep the license and source information at the top of the file.

## 2. Main plugin file

Create `my-plugin.php`. This file loads the copied container and the plugin autoloader, configures the container, and starts the plugin.

```php
<?php
/**
 * Plugin Name: LiteWire DI Example
 * Description: A small plugin built with constructor injection.
 * Requires PHP: 8.1
 * Version: 1.0.0
 */
 
namespace Example\MyPlugin;

defined( 'ABSPATH' ) || exit;

require_once __DIR__ . '/lib/Container.php';
require_once __DIR__ . '/autoload.php';

add_action( 'plugins_loaded', 'example_my_plugin_init' );

function example_my_plugin_init(): void {
	$container = new \Kama\LiteWireDI\Container();

	// The container cannot guess which class should implement an interface.
	$container->set( Logger::class, WordPressLogger::class );

	// The container cannot guess scalar values such as file paths and slugs.
	$container->set(
		PluginConfig::class,
		new PluginConfig( [
			'plugin_file' => __FILE__,
			'option_name' => 'lwdi_message',
			'menu_slug'   => 'litewire-di-example',
		] )
	);

	// Plugin and its remaining dependencies are created automatically.
	$container->get( Plugin::class )->boot();
}
```

This is the composition root: the one place where the application is assembled. Interface bindings and project values belong here.

Configure everything before calling `get()`. `get()` stores shared objects, so changing a definition later will not update objects which were already created.


## 3. Autoloader

This example does not use Composer. The small autoloader maps the `Example\MyPlugin` namespace to the `src` directory.

Create `autoload.php`:

```php
<?php
namespace Example\MyPlugin;

spl_autoload_register( static function ( string $class ): void {
	if ( ! str_starts_with( $class, __NAMESPACE__ . '\\' ) ) {
		return;
	}

	$path = str_replace( [ __NAMESPACE__, '\\' ], [ __DIR__ . '/src', '/' ], $class );

	require_once "$path.php";
} );
```


## 4. Plugin configuration object

Create `src/PluginConfig.php`:

```php
<?php
namespace Example\MyPlugin;

final class PluginConfig {

	public readonly string $plugin_file;
	public readonly string $option_name;
	public readonly string $menu_slug;

	public function __construct( array $config ) {
		$this->plugin_file = $config['plugin_file'];
		$this->option_name = $config['option_name'];
		$this->menu_slug   = $config['menu_slug'];
	}
	
}
```

A configuration object is better than asking the container for string keys. It gives the values clear names and keeps them type-safe.

## 5. Logger interface and implementation

Create `src/Logger/Logger.php`:

```php
<?php
namespace Example\MyPlugin\Logger;

interface Logger {
	public function info( string $message ): void;
}
```

Create `src/Logger/WordPressLogger.php`:

```php
<?php
namespace Example\MyPlugin\Logger;

final class WordPressLogger implements Logger {
	public function info( string $message ): void {
		error_log( '[LiteWire DI Example] ' . $message );
	}
}
```

The plugin classes depend on `Logger`, not on `WordPressLogger`. Production uses the WordPress implementation. A test can pass a small fake logger.

## 6. Settings page

Create `src/SettingsPage.php`:

```php
<?php

namespace Example\MyPlugin;

use Example\MyPlugin\Logger\Logger;

final class SettingsPage {

	public function __construct( 
		private readonly PluginConfig $config, 
		private readonly Logger $logger
	) {
	}

	public function register_hooks(): void {
		add_action( 'admin_menu', [ $this, 'add_page' ] );
		add_action( 'admin_init', [ $this, 'register_setting' ] );
		
		$basename = plugin_basename( $this->config->plugin_file );
		add_filter( "plugin_action_links_$basename", [ $this, 'add_action_link' ] );
	}

	public function add_action_link( array $links ): array {
		$url = admin_url( "options-general.php?page={$this->config->menu_slug}" );
		array_unshift( $links, '<a href="' . esc_url( $url ) . '">Settings</a>' );

		return $links;
	}

	public function add_page(): void {
		add_options_page(
			'Example message',
			'Example message',
			'manage_options',
			$this->config->menu_slug,
			[ $this, 'render' ]
		);
	}

	public function register_setting(): void {
		register_setting(
			$this->config->menu_slug,
			$this->config->option_name,
			[ 'sanitize_callback' => [ $this, 'sanitize' ] ]
		);
	}

	public function sanitize( $value ): string {
		$message = sanitize_text_field( (string) $value );
		$this->logger->info( 'The admin message was updated.' );

		return $message;
	}

	public function render(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$value = get_option( $this->config->option_name, '' );
		?>
		<div class="wrap">
			<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
			<form action="options.php" method="post">
				<?php settings_fields( $this->config->menu_slug ); ?>
				<label for="lwdi-message">Message</label>
				<input
					id="lwdi-message"
					class="regular-text"
					name="<?php echo esc_attr( $this->config->option_name ); ?>"
					value="<?php echo esc_attr( (string) $value ); ?>"
				>
				<?php submit_button(); ?>
			</form>
		</div>
		<?php
	}
}
```

`SettingsPage` asks for `PluginConfig` and `Logger`. The bootstrap registered both. LiteWire DI passes them into the constructor.

## 7. Admin notice

Create `src/AdminNotice.php`:

```php
<?php

namespace Example\MyPlugin;

final class AdminNotice {

	public function __construct( 
		private readonly PluginConfig $config
	) {
	}

	public function register_hooks(): void {
		add_action( 'admin_notices', [ $this, 'render' ] );
	}

	public function render(): void {
		$message = get_option( $this->config->option_name, '' );

		if ( ! is_string( $message ) || $message === '' ) {
			return;
		}
		?>
		<div class="notice notice-info">
			<p><?php echo esc_html( $message ); ?></p>
		</div>
		<?php
	}
	
}
```

`AdminNotice` uses the same shared `PluginConfig` object as `SettingsPage`.

## 8. Plugin controller

Create `src/Plugin.php`:

```php
<?php

namespace Example\MyPlugin;

final class Plugin {

	public function __construct(
		private readonly SettingsPage $settings_page,
		private readonly AdminNotice $admin_notice
	) {
	}

	public function boot(): void {
		$this->settings_page->register_hooks();
		$this->admin_notice->register_hooks();
	}
	
}
```

The bootstrap only asks for `Plugin`. LiteWire DI follows the constructors and builds this graph:

```text
Plugin
├── SettingsPage
│   ├── PluginConfig
│   └── Logger → WordPressLogger
└── AdminNotice
    └── PluginConfig (same shared object)
```

## How to add another service

For a concrete class, add it to a constructor. No bootstrap change is needed:

```php
public function __construct( 
	private readonly PluginConfig $config,
	private readonly MessageRepository $messages
) {
}
```

Register something only when LiteWire DI cannot decide how to create it:

- bind an interface to a concrete class with `set()`;
- register an existing configured object;
- use a factory for scalar values or third-party APIs.

## Testing a class without the container

The container is object setup, not a requirement inside your classes. A unit test can create `SettingsPage` directly:

```php
$config = new PluginConfig( [
	'plugin_file' => '/tmp/my-plugin.php',
	'option_name' => 'test-option',
	'menu_slug'   => 'test-menu',
] );
$logger = new FakeLogger();
$page = new SettingsPage( $config, $logger );
```

This is the main reason to keep dependencies in constructors: they are easy to see and easy to replace in tests.

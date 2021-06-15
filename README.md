# Tribe Storage

This WordPress plugin works as a bridge to use [Flysystem](https://flysystem.thephpleague.com/v1/docs/) adapters (version 1) 
within WordPress. This plugin is meant to be installed and configured by **developers**, as it has no interface.

## Currently Supported Adapters

- [Local](https://flysystem.thephpleague.com/v1/docs/adapter/local/)
- [Azure Storage](https://flysystem.thephpleague.com/v1/docs/adapter/azure/)

## Recommendations

**It is highly recommended to set your `WP_CONTENT_URL` to the base site on multisite.**

In `wp-config.php`

```php
function tribe_isSSL() {
	return ( ! empty( $_SERVER['HTTPS'] ) && $_SERVER['HTTPS'] !== 'off' );
}

define( 'WP_CONTENT_URL', ( tribe_isSSL() ? 'https' : 'http' ) . '://' . $_SERVER['HTTP_HOST'] . '/wp-content' );
```

## Installation

**Requirements**
- PHP7.3+
- [Composer](https://getcomposer.org/)
- [Composer Installers](https://composer.rarst.net/recipe/paths-control/)
- The server should have PHP compiled with [ImageMagick](https://www.php.net/manual/en/book.imagick.php) or
[GD](https://www.php.net/manual/en/book.image.php).

### Install with Composer

```shell
composer require moderntribe/tribe-storage
```

## Adapters

Adapters allow different interfaces to different storage providers. In order to tell the system which adapter to use,
add a `TRIBE_STORAGE_ADAPTER` define() to `wp-config.php` with the namespaced path to the adapter 
`(same output as Class_Name::class!)`, e.g to use the Azure Storage Adapter: 

`define( 'TRIBE_STORAGE_ADAPTER', 'Tribe\Storage\Adapters\Azure_Adapter' );`

### Local Adapter

This is the default adapter and is pointed to `WP_CONTENT_DIR . /uploads`. If you have not configured any custom 
adapters, this will automatically be used and should function exactly as WordPress does out of the box.

**NOTE:** A misconfigured adapter will always use the Local Adapter and show a notice in the WordPress Dashboard.

### Azure Storage Adapter

To configure this Adapter, add and customize the following defines to `wp-config.php`:

```php
// Account name as you created it in the Azure dashboard.
define( 'MICROSOFT_AZURE_ACCOUNT_NAME', 'account' );

// Account secret key to authenticate.
define( 'MICROSOFT_AZURE_ACCOUNT_KEY', 'key' );

// The container name.
define( 'MICROSOFT_AZURE_CONTAINER', 'my_container' );

// The custom adapter namespaced path to the adapter.
define( 'TRIBE_STORAGE_ADAPTER', 'Tribe\Storage\Adapters\Azure_Adapter' );

// The URL/CNAME to use for the adapter.
define( 'TRIBE_STORAGE_URL', 'https://example.com/wp-content/uploads/' . MICROSOFT_AZURE_CONTAINER );
```

### Image Editor Customization

Force a custom Image Editor Strategy if Imagick or GD are experiencing issues like 500 errors with no logs.

```php
// For GD
define( 'TRIBE_STORAGE_IMAGE_EDITOR', 'gd' );

// For Imagick
define( 'TRIBE_STORAGE_IMAGE_EDITOR', 'imagick' );
```

### Caching

#### Transient Database Caching (default)

Caching is saved via WordPress transients, automatically forced to use the database even if external object
caching is available. The Flysystem cache adapters unfortunately save the entire output into a single key.

If you're using Redis or Memcached with a greater than 1mb limit, you can disable this with:

```
// Store transients in the object cache instead of the database.
add_filter( 'tribe/storage/config/cache/force_db', '__return_false' );
```

##### Disable Caching

If you have any issues with the cache, you can disable it by adding the following to `wp-config.php`:

`define( 'TRIBE_STORAGE_NO_CACHE', true );`

### Automated Testing

Testing provided via [PHPUnit](https://phpunit.de/) and the [Brain Monkey](https://brain-wp.github.io/BrainMonkey/) 
testing suite.

#### Run Unit Tests

- composer install
- ./vendor/bin/phpunit

### Stream Wrapper

This package includes an overridden version of [twistor/flysystem-stream-wrapper](https://github.com/twistor/flysystem-stream-wrapper)
which creates a stream wrapper of `fly://path/to/file.jpg`.

### Adding Flysystem Adapters

We need to bridge existing adapters to be used with WordPress. See [/src/Adapters/Adapter.php](./src/Adapters/Adapter.php). 
An Adapter should extend this abstract.

`Adapter::get(): AdapterInterface;`

This should return the configured adapter.

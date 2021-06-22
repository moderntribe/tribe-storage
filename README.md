# Tribe Storage

[![PHPCS + Unit Tests](https://github.com/moderntribe/tribe-storage/actions/workflows/pull-request.yml/badge.svg)](https://github.com/moderntribe/tribe-storage/actions/workflows/pull-request.yml)
![php 7.3+](https://img.shields.io/badge/php-min%207.3-red.svg)

This WordPress plugin works as a bridge to use [Flysystem](https://flysystem.thephpleague.com/v1/docs/) adapters (v1) 
within WordPress. This plugin is meant to be installed and configured by **developers**, as it has no GUI.

## Currently Supported Adapters

- [Local](https://flysystem.thephpleague.com/v1/docs/adapter/local/)
- [Azure Storage](https://flysystem.thephpleague.com/v1/docs/adapter/azure/)

## Recommendations

**It is highly recommended to set your `WP_CONTENT_URL` to the base site on a multisite installation.**

In `wp-config.php`:

```php
function tribe_isSSL(): bool {
	return ( ! empty( $_SERVER['HTTPS'] ) && $_SERVER['HTTPS'] !== 'off' );
}

define( 'WP_CONTENT_URL', ( tribe_isSSL() ? 'https' : 'http' ) . '://' . $_SERVER['HTTP_HOST'] . '/wp-content' );
```

## Installation

**Requirements**
- PHP7.3+
- WordPress 5.6+
- [Composer](https://getcomposer.org/)
- [Composer Installers](https://composer.rarst.net/recipe/paths-control/)
- The server should have PHP compiled with [ImageMagick](https://www.php.net/manual/en/book.imagick.php) or
[GD](https://www.php.net/manual/en/book.image.php).

### Install with Composer v1

Add the following to the composer.json repositories object:

```json
  "repositories": [
      {
        "type": "vcs",
        "url": "git@github.com:moderntribe/tribe-storage.git"
      },
  ]

```
Then run:

```shell
composer require moderntribe/tribe-storage
```

## Adapters

Adapters allow different interfaces to different storage providers. In order to tell the system which adapter to use,
add a `TRIBE_STORAGE_ADAPTER` define() to `wp-config.php` with the namespaced path to the adapter 
`(same output as Class_Name::class)`, e.g to use the Azure Storage Adapter: 

`define( 'TRIBE_STORAGE_ADAPTER', 'Tribe\Storage\Adapters\Azure_Adapter' );`

### Local Adapter

This is the default adapter and is pointed to `WP_CONTENT_DIR . /uploads`. If you have not configured any custom 
adapters, this will automatically be used and should function exactly as WordPress does out of the box.

> **NOTE:** A misconfigured adapter will always use the Local Adapter and show a notice in the WordPress Dashboard.

### Azure Storage Adapter

To configure this Adapter, add and customize the following defines to your `wp-config.php`:

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

If you're using Redis or Memcached (with a much greater than a 1mb limit), you can disable this with:

```php
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

```bash
$ composer install
$ ./vendor/bin/phpunit
```

### Adding Flysystem Adapters

Create a Flysystem bridge. See [/src/Adapters/Adapter.php](./src/Adapters/Adapter.php).

`Adapter::get(): AdapterInterface;`

The `get()` method should return a configured Flysystem adapter.

## More Resources:
- [Tribe Storage Statically.io CDN](https://github.com/moderntribe/tribe-storage-statically-cdn)
- [Modern Tribe](https://tri.be/)

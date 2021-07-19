<?php declare(strict_types=1);

namespace Tribe\Storage\Uploads;

use Intervention\Image\Exception\NotSupportedException;
use Intervention\Image\ImageManager;
use InvalidArgumentException;
use League\Flysystem\Filesystem;
use Throwable;

/**
 * Class Upload_Manager.
 *
 * @package Tribe\Storage\Uploads
 */
class Upload_Manager {

	/**
	 * The filesystem instance.
	 *
	 * @var \League\Flysystem\Filesystem
	 */
	protected $filesystem;

	/**
	 * The original wp_upload_dir data before it was modified.
	 *
	 * @var \Tribe\Storage\Uploads\Wp_Upload_Dir
	 */
	protected $upload_dir;

	/**
	 * The Intervention Image Manager.
	 *
	 * @var \Intervention\Image\ImageManager
	 */
	protected $image_manager;

	/**
	 * Upload_Manager constructor.
	 *
	 * @param  \League\Flysystem\Filesystem          $filesystem
	 * @param  \Tribe\Storage\Uploads\Wp_Upload_Dir  $upload_dir
	 * @param  \Intervention\Image\ImageManager      $image_manager
	 */
	public function __construct( Filesystem $filesystem, Wp_Upload_Dir $upload_dir, ImageManager $image_manager ) {
		$this->filesystem    = $filesystem;
		$this->upload_dir    = $upload_dir;
		$this->image_manager = $image_manager;
	}

	/**
	 * Images with invalid orientation exif data fail to make thumbnails.
	 *
	 * @filter pre_move_uploaded_file
	 *
	 * @param  mixed     $move_new_file  If null (default) move the file after the upload.
	 * @param  string[]  $file           An array of data for a single file.
	 * @param  string    $new_file       Filename of the newly-uploaded file.
	 * @param  string    $type           Mime type of the newly-uploaded file.
	 *
	 * @throws \Exception|\Throwable
	 *
	 * @return null
	 */
	public function fix_image_orientation( $move_new_file, array $file, string $new_file, string $type ) {
		/**
		 * Allow users to bypass this fix.
		 *
		 * @param  bool  $bypass  Bypass the image orientation fix.
		 */
		$bypass = (bool) apply_filters( 'tribe/storage/bypass_image_orientation', false, $move_new_file, $file, $new_file, $type );

		if ( true === $bypass ) {
			return $move_new_file;
		}

		$file_path = $file['tmp_name'] ?? '';

		if ( ! empty( $file_path ) ) {
			$backup_driver  = 'gd';
			$current_driver = $this->image_manager->config['driver'] ?? '';

			if ( 'gd' === $current_driver ) {
				$backup_driver = 'imagick';
			}

			// Try the currently configured driver and fallback to the alternative.
			try {
				$image = $this->image_manager->make( $file_path );
			} catch ( NotSupportedException $e ) {
				try {
					$this->image_manager = $this->image_manager->configure( [ 'driver' => $backup_driver ] );
					$image               = $this->image_manager->make( $file_path );
				} catch ( NotSupportedException $e ) {
					if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
						throw $e;
					}
				}
			}

			try {
				// Fix image orientation based on existing exif data
				$image->orientate();
				$image->save( $file_path );
			} catch ( Throwable $e ) {
				if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
					throw $e;
				}
			}
		}

		return $move_new_file;
	}

	/**
	 * Update the WordPress wp_upload_dir() data to use Flysystem.
	 *
	 * @filter upload_dir
	 *
	 * @param  array  $dir  The wp_upload_dir() array
	 *
	 * @return array The modified wp_upload_dir() array
	 */
	public function upload_dir( array $dir ): array {

		// Add the original wp_upload_dir() output to the stack.
		$this->upload_dir->add_dir( $dir );

		// Set the default URL to a user provided CDN/CNAME
		if ( defined( 'TRIBE_STORAGE_URL' ) && TRIBE_STORAGE_URL ) {
			$default_url = TRIBE_STORAGE_URL;
		} else {
			$default_url = $this->upload_dir->original_dir( get_current_blog_id() )['baseurl'];
		}

		/**
		 * Get the Tribe Storage upload url.
		 *
		 * @param  string  $url
		 */
		$url = apply_filters( 'tribe/storage/upload/url', $default_url );
		$url = is_string( $url ) ? $url : $default_url;
		$url = rtrim( $url, '/' );

		/**
		 * Get The Tribe storage base path.
		 *
		 * @param  string  $path
		 */
		$base_path = apply_filters( 'tribe/storage/upload/base_path', apply_filters( 'tribe/storage/stream_name', 'fly' ) . '://' );
		$base_path = is_string( $base_path ) ? $base_path : apply_filters( 'tribe/storage/stream_name', 'fly' ) . '://';
		$base_path = $base_path[ strlen( $base_path ) - 1 ] === '/' ? str_replace( '://', ':/', $base_path ) : $base_path;

		// Replace the uploads directory with the stream wrapper
		$dir['path']    = str_replace( WP_CONTENT_DIR . '/uploads', $base_path, $dir['path'] );
		$dir['basedir'] = str_replace( WP_CONTENT_DIR . '/uploads', $base_path, $dir['basedir'] );

		// Replace TRIBE_STORAGE_URL in the URL/baseurl
		if ( $url !== $dir['baseurl'] ) {
			$dir['url']     = str_replace( $base_path, $url, $dir['path'] );
			$dir['baseurl'] = str_replace( $base_path, $url, $dir['basedir'] );
		}

		// Fix potential 'uploads/uploads'
		$uploads        = defined( 'UPLOADS' ) ? UPLOADS : '/uploads';
		$dir['url']     = str_replace( $uploads . $uploads, $uploads, $dir['url'] );
		$dir['baseurl'] = str_replace( $uploads . $uploads, $uploads, $dir['baseurl'] );

		return $dir;
	}

	/**
	 * Allow developers to force a specific Image Editor strategy.
	 *
	 * @filter wp_image_editors
	 *
	 * @param  string[]  $editors     Array of available image editor class names. Defaults are
	 *                                'WP_Image_Editor_Imagick', 'WP_Image_Editor_GD'.
	 *
	 * @return array
	 *
	 * @throws \InvalidArgumentException
	 */
	public function image_editors( array $editors ): array {
		if ( ! defined( 'TRIBE_STORAGE_IMAGE_EDITOR' ) || ! TRIBE_STORAGE_IMAGE_EDITOR ) {
			return (array) $editors;
		}

		if ( 'gd' !== TRIBE_STORAGE_IMAGE_EDITOR && 'imagick' !== TRIBE_STORAGE_IMAGE_EDITOR ) {
			throw new InvalidArgumentException(
				__( 'Invalid image editor defined for TRIBE_STORAGE_IMAGE_EDITOR. Options are: "gd" or "imagick"', 'tribe-storage' )
			);
		}

		if ( 'gd' === TRIBE_STORAGE_IMAGE_EDITOR ) {
			return [ 'WP_Image_Editor_GD' ];
		}

		return [ 'WP_Image_Editor_Imagick' ];
	}

	/**
	 * Prevent WordPress from doing full directory listings on remote storage
	 * when uploading files.
	 *
	 * @filter get_files_for_unique_filename_file_list
	 *
	 * @param  array|null  $files
	 * @param  string      $dir
	 * @param  string      $filename
	 *
	 * @return array
	 */
	public function bypass_directory_listing( ?array $files, string $dir, string $filename ): array {
		$full_path = trailingslashit( $dir ) . $filename;
		$exists    = $this->filesystem->has( $full_path );

		// This file exists, return it to WordPress so it can make it unique
		if ( $exists ) {
			return [
				$filename,
			];
		}

		// Default to a file that is highly unlikely to exist, so WordPress will do its regular processing
		return [
			$this->get_fake_file_name(),
		];
	}

	/**
	 * Create a file name that is highly unlikely to exist on remote storage.
	 *
	 * @return string
	 */
	protected function get_fake_file_name(): string {
		return (string) apply_filters(
			'tribe/storage/upload/fake_file_name',
			'3debf56855bad8fa0d38d4eb45efe98432d549612703f0b04f7e2ebe9ef28a863fdffc5a8b322524712cf26f5e7efc4ea5a19255f0d30a527e9306b7ee49e2d3.jpg'
		);
	}

}

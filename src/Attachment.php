<?php declare(strict_types=1);

namespace Tribe\Storage;

use League\Flysystem\Filesystem;
use Throwable;
use Tribe\Storage\Uploads\Wp_Upload_Dir;

/**
 * Class Attachment.
 *
 * @package Tribe\Storage
 */
class Attachment {

	/**
	 * The filesystem instance.
	 *
	 * @var \League\Flysystem\Filesystem
	 */
	protected $filesystem;

	/**
	 * The original wp_upload_dir data before it was modified.
	 *
	 * @var string[]|\Tribe\Storage\Uploads\Wp_Upload_Dir
	 */
	protected $upload_dir;

	/**
	 * Attachment constructor.
	 *
	 * @param  \League\Flysystem\Filesystem          $filesystem
	 * @param  \Tribe\Storage\Uploads\Wp_Upload_Dir  $upload_dir
	 */
	public function __construct( Filesystem $filesystem, Wp_Upload_Dir $upload_dir ) {
		$this->filesystem = $filesystem;
		$this->upload_dir = $upload_dir;
	}

	/**
	 * Get the Tribe Storage attachment url.
	 *
	 * @filter wp_get_attachment_url
	 *
	 * @param  string  $url
	 * @param  int     $attachment_id
	 *
	 * @return string
	 */
	public function attachment_url( string $url, int $attachment_id ): string {
		$dir = wp_upload_dir( null, true );

		// Only replace the base URL if it doesn't already exist in the existing URL.
		if ( strpos( $url, $dir['baseurl'] ) === false ) {
			$url = str_replace( $this->upload_dir->original_dir( get_current_blog_id() )['baseurl'], $dir['baseurl'], $url );
		}

		/**
		 * Modify the storage attachment url.
		 *
		 * @param  string                              $url
		 * @param  int                                 $attachment_id
		 * @param  \League\Flysystem\AdapterInterface  $adapter
		 */
		return apply_filters( 'tribe/storage/attachment_url', $url, $attachment_id, $this->filesystem->getAdapter() );
	}

	/**
	 * Prevent WordPress from reading the filesize of every image by providing a dummy
	 * size for images missing the meta option.
	 *
	 * @param  array|bool  $meta
	 * @param  int         $attachment_id
	 *
	 * @return array|bool
	 *
	 * @see    wp_prepare_attachment_for_js()
	 *
	 * @filter wp_get_attachment_metadata
	 */
	public function get_metadata( $meta, int $attachment_id ) {
		if ( ! is_array( $meta ) ) {
			return $meta;
		}

		// If there are sizes, this is an image.
		if ( isset( $meta['sizes'] ) && empty( $meta['filesize'] ) ) {
			$meta['filesize'] = 350000;
		}

		return $meta;
	}

	/**
	 * Add an image's filesize to the attachment meta to prevent WordPress from running i/o
	 * operations.
	 *
	 * @param  array  $meta
	 * @param  int    $attachment_id
	 *
	 * @return array
	 *
	 * @see    wp_prepare_attachment_for_js()
	 *
	 * @filter wp_update_attachment_metadata
	 */
	public function update_metadata( array $meta, int $attachment_id ): array {
		if ( empty( $meta['filesize'] ) && ! empty( $meta['file'] ) ) {
			try {
				// Prefix file path with sites/<site_id> to get the correct path in flysystem
				$upload_dir = wp_get_upload_dir();
				$stream     = apply_filters( 'tribe/storage/stream_name', 'fly' ) . '://';
				$file       = trailingslashit( str_replace( $stream, '', $upload_dir['basedir'] ) ) . $meta['file'];
				// Root site comes in as fly:/
				$file       = str_replace( substr( $stream, 0, -1 ), '', $file );
				$image_data = $this->filesystem->getAdapter()->getMetadata( $file );

				if ( ! empty( $image_data['size'] ) ) {
					$meta['filesize'] = $image_data['size'];
				}
			} catch ( Throwable $e ) {
				return $meta;
			}
		}

		return $meta;
	}

}

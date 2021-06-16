<?php declare(strict_types=1);

namespace Tribe\Storage\Image_Editors;

use Imagick;
use WP_Error;
use WP_Image_Editor_Imagick;

/**
 * Class Image_Editor_Imagick.
 *
 * @package Tribe\Storage\Image_Editors
 */
class Image_Editor_Imagick extends WP_Image_Editor_Imagick {

	/**
	 * Hold on to a reference of all temp local files.
	 *
	 * These are cleaned up on __destruct.
	 *
	 * @var string[]
	 */
	protected $temp_files = [];

	/**
	 * Remove temporary files.
	 */
	public function __destruct() {
		array_map( 'unlink', $this->temp_files );
		parent::__destruct();
	}

	/**
	 * Loads the image from $this->file into a new Imagick Object.
	 *
	 * @return true|\WP_Error True if loaded; WP_Error on failure.
	 */
	public function load() {
		if ( $this->image instanceof Imagick ) {
			return true;
		}

		if ( ! is_file( $this->file ) && ! preg_match( '|^https?://|', $this->file ) ) {
			return new WP_Error( 'error_loading_image', __( 'File doesn&#8217;t exist?' ), $this->file );
		}

		$upload_dir = wp_upload_dir();

		if ( strpos( $this->file, $upload_dir['basedir'] ) !== 0 ) {
			return parent::load();
		}

		$temp_filename      = tempnam( get_temp_dir(), 'flysystem' );
		$this->temp_files[] = $temp_filename;

		copy( $this->file, $temp_filename );

		$this->remote_filename = $this->file;

		$this->file = $temp_filename;

		$result = parent::load();

		$this->file = $this->remote_filename;

		return $result;
	}

	/**
	 * Imagick can't handle stream wrappers, need to create a temporary file and then copy it
	 * up to the Adapter's storage.
	 *
	 * @param \Imagick $image
	 * @param  string   $filename
	 * @param  string   $mime_type
	 *
	 * @return array|\WP_Error
	 */
	protected function _save( $image, $filename = null, $mime_type = null ) { // phpcs:ignore
		[ $filename, $extension, $mime_type ] = $this->get_output_format( $filename, $mime_type );

		if ( empty( $filename ) ) {
			$filename = $this->generate_filename( null, null, $extension );
		}

		$upload_dir = wp_upload_dir();

		$temp_filename = '';

		if ( strpos( $filename, $upload_dir['basedir'] ) === 0 ) {
			$temp_filename = tempnam( get_temp_dir(), 'flysystem' );
		}

		$save = parent::_save( $image, $temp_filename, $mime_type );

		if ( is_wp_error( $save ) ) {
			if ( $temp_filename ) {
				unlink( $temp_filename );
			}

			return $save;
		}

		$copy_result = copy( $save['path'], $filename );

		unlink( $save['path'] );
		unlink( $temp_filename );

		if ( ! $copy_result ) {
			return new WP_Error( 'unable-to-copy-tribe-storage', __( 'Unable to copy the temp image to adapter storage using Tribe Storage', 'tribe' ) );
		}

		return [
			'path'      => $filename,
			'file'      => wp_basename( apply_filters( 'image_make_intermediate_size', $filename ) ),
			'width'     => $this->size['width'],
			'height'    => $this->size['height'],
			'mime-type' => $mime_type,
		];
	}

}

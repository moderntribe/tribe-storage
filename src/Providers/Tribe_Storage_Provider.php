<?php declare(strict_types=1);

namespace Tribe\Storage\Providers;

use Throwable;
use Tribe\Storage\Attachment;
use Tribe\Storage\Notice;
use Tribe\Storage\Uploads\Upload_Manager;

/**
 * Service Provider for the Tribe Storage plugin.
 *
 * @package Tribe\Storage\Providers
 */
class Tribe_Storage_Provider implements Providable {

	/**
	 * @var \Tribe\Storage\Uploads\Upload_Manager
	 */
	private $upload_dir;

	/**
	 * @var \Tribe\Storage\Attachment
	 */
	private $attachment;

	/**
	 * @var \Tribe\Storage\Notice
	 */
	private $notice;

	/**
	 * Tribe_Storage_Provider constructor.
	 *
	 * @param  \Tribe\Storage\Uploads\Upload_Manager  $upload_dir
	 * @param  \Tribe\Storage\Attachment              $attachment
	 * @param  \Tribe\Storage\Notice                  $notice
	 */
	public function __construct( Upload_Manager $upload_dir, Attachment $attachment, Notice $notice ) {
		$this->upload_dir = $upload_dir;
		$this->attachment = $attachment;
		$this->notice     = $notice;
	}

	/**
	 * Register hooks.
	 */
	public function register(): void {
		$this->register_attachment_hooks();
		$this->register_upload_hooks();
		$this->register_notice_hooks();
	}

	/**
	 * Register Attachment related hooks.
	 */
	private function register_attachment_hooks(): void {
		add_filter( 'wp_get_attachment_url', function ( string $url, int $attachment_id ) {
			return $this->attachment->attachment_url( $url, $attachment_id );
		}, 10, 2 );

		add_filter( 'wp_get_attachment_metadata', function ( $meta, int $attachment_id ) {
			return $this->attachment->get_metadata( $meta, $attachment_id );
		}, 10, 2 );

		add_filter( 'wp_update_attachment_metadata', function ( array $meta, int $attachment_id ) {
			return $this->attachment->update_metadata( $meta, $attachment_id );
		}, 10, 2 );
	}

	/**
	 * Register Upload related hooks.
	 */
	private function register_upload_hooks(): void {
		// Only run image orientation fix if developers have enabled this feature.
		if ( defined( 'TRIBE_STORAGE_IMAGE_ORIENTATION' ) && TRIBE_STORAGE_IMAGE_ORIENTATION ) {
			add_filter( 'pre_move_uploaded_file', function ( $move_new_file, array $file, string $new_file, string $type ) {
				return $this->upload_dir->fix_image_orientation( $move_new_file, $file, $new_file, $type );
			}, 10, 4 );
		}

		add_filter( 'upload_dir', function ( array $dirs ) {
			return $this->upload_dir->upload_dir( $dirs );
		}, 10, 1 );

		add_filter( 'wp_image_editors', function ( array $editors ) {
			return $this->upload_dir->image_editors( $editors );
		}, 9, 1 );

		add_filter( 'pre_wp_unique_filename_file_list', function ( $files, $dir, $filename ) {
			return $this->upload_dir->filter_unique_file_list( $files, (string) $dir, (string) $filename );
		}, 10, 3);
	}

	/**
	 * Register admin notice hooks
	 */
	private function register_notice_hooks(): void {
		// Show an admin notice if a defined adapter was not properly configured
		add_action( 'tribe/storage/adapter_error', function ( Throwable $e ): void {
			add_action( 'admin_notices', function () use ( $e ): void {
				$this->notice->print_notices( $e );
			}, 10, 0 );

			add_action( 'network_admin_notices', function () use ( $e ): void {
				$this->notice->print_notices( $e );
			}, 10, 0 );
		}, 10, 1 );
	}

}

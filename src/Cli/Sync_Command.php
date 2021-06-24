<?php declare(strict_types=1);

namespace Tribe\Storage\Cli;

use League\Flysystem\Filesystem;
use Throwable;
use Tribe\Storage\Uploads\Wp_Upload_Dir;
use WP_CLI;
use WP_Query;

/**
 * Class Sync_Command
 *
 * @package Tribe\Storage\Cli
 */
class Sync_Command extends Command {

	public const ARG_SITE_IDS    = 'site-ids';
	public const ARG_SHOW_DETAIL = 'show-detail';
	public const ARG_DRY_RUN     = 'dry-run';

	public const LOG_INFO    = 'info';
	public const LOG_WARNING = 'warning';

	/**
	 * @var \Tribe\Storage\Uploads\Wp_Upload_Dir
	 */
	private $upload_dir;

	/**
	 * @var \League\Flysystem\Filesystem
	 */
	private $filesystem;

	/**
	 * A multidimensional array of info/warning log messages.
	 *
	 * @var string[][]
	 */
	private $log = [];

	public function __construct( Wp_Upload_Dir $upload_dir, Filesystem $filesystem ) {
		$this->upload_dir = $upload_dir;
		$this->filesystem = $filesystem;
		parent::__construct();
	}

	/**
	 * wp tribe-storage sync
	 *
	 * @param  array  $args
	 * @param  array  $assoc_args
	 *
	 * @throws \WP_CLI\ExitException
	 */
	public function run_command( array $args = [], array $assoc_args = [] ): void {
		$show_detail = WP_CLI\Utils\get_flag_value( $assoc_args, self::ARG_SHOW_DETAIL, false );
		$dry_run     = WP_CLI\Utils\get_flag_value( $assoc_args, self::ARG_DRY_RUN, false );

		if ( $dry_run ) {
			WP_CLI::log( __( '* Dry run enabled', 'tribe-storage' ) );
		}

		if ( is_multisite() ) {
			$site_args = [
				'number' => 99999,
			];

			if ( ! empty( $args ) ) {
				$site_args = array_merge( $site_args, [
					'site__in' => $args,
				] );
			}

			$sites = get_sites( $site_args );

			foreach ( $sites as $site ) {
				switch_to_blog( $site->blog_id );
				$this->process_attachment( $show_detail, $dry_run, (int) $site->blog_id );
				restore_current_blog();
			}
		} else {
			$this->process_attachment( $show_detail, $dry_run, 1 );
		}

		WP_CLI::success( __( 'All done! Have a nice day ツ', 'tribe-storage' ) );
	}

	/**
	 * Attempt to upload an attachment to remote storage.
	 *
	 * @param  bool  $show_detail Whether to show the user more detail during execution.
	 * @param  bool  $dry_run Do not actually copy the file.
	 * @param  int   $blog_id The current blog ID being processed.
	 *
	 * @throws \WP_CLI\ExitException
	 */
	protected function process_attachment( bool $show_detail, bool $dry_run, int $blog_id ): void {
		$query = new WP_Query( [
			'post_type'      => 'attachment',
			'post_status'    => 'inherit',
			'posts_per_page' => - 1,
		] );

		if ( empty( $query->posts ) ) {
			WP_CLI::error( __( 'No attachments found. Aborting.', 'tribe-storage' ) );
		}

		WP_CLI::log( sprintf( __( 'Found %d attachments for blog_id: %d', 'tribe-storage' ), count( $query->posts ), $blog_id ) );

		$progress = WP_CLI\Utils\make_progress_bar( __( 'Syncing images to your cloud provider', 'tribe-storage' ), count( $query->posts ) );

		$upload_dir = wp_get_upload_dir();

		/** @var \WP_Post $attachment */
		foreach ( $query->posts as $attachment ) {
			// Return the upload dir to the original before being modified by tribe storage
			add_filter( 'upload_dir', function ( array $dirs ) {
				return $this->upload_dir->original_dir( get_current_blog_id() );
			} );

			$file = get_attached_file( $attachment->ID );

			if ( empty( $file ) ) {
				$this->log[ self::LOG_WARNING ][] = sprintf( __( '[ATTACHMENT ID: %d]: No source file found. Skipped.', 'tribe-storage' ), $attachment->ID );
				continue;
			}

			if ( ! file_exists( $file ) ) {
				$this->log[ self::LOG_WARNING ][] = sprintf( __( '[ATTACHMENT ID: %d]: File "%s" does not exist on the server. Skipped.', 'tribe-storage' ), $attachment->ID, $file );
				continue;
			}

			if ( $show_detail ) {
				$this->log[ self::LOG_INFO ][] = sprintf( __( '[ATTACHMENT ID: %d]: Importing file: %s to your cloud provider.', 'tribe-storage' ), $attachment->ID, $file );
			}

			if ( $dry_run ) {
				$this->log[ self::LOG_INFO ][] = sprintf( __( '[DRY RUN, ATTACHMENT ID: %d]: Would attempt to upload file "%s" to your cloud provider.', 'tribe-storage' ), $attachment->ID, $file );
			} else {
				// Return upload dir back to flysystem
				add_filter( 'upload_dir', static function ( array $dirs ) use ( $upload_dir ) {
					return $upload_dir;
				} );

				$remote_file = get_attached_file( $attachment->ID );

				if ( file_exists( $remote_file ) ) {
					$this->log[ self::LOG_WARNING ][] = sprintf( __( '[ATTACHMENT ID: %d]: File "%s" already exists on cloud provider. Skipped.', 'tribe-storage' ), $attachment->ID, $file );
					continue;
				}

				try {
					$stream = fopen( $file, 'r+' );
					$this->filesystem->writeStream( $this->get_target( $remote_file ), $stream );

					if ( is_resource( $stream ) ) {
						fclose( $stream );
					}
				} catch ( Throwable $e ) {
					$this->log[ self::LOG_WARNING ][] = sprintf( __( '[ATTACHMENT ID: %d, FILE: %s]: An error occurred copying to cloud provider: %s.', 'tribe-storage' ), $attachment->ID, $file, $e->getMessage() );
					continue;
				}
			}

			$progress->tick();

			WP_CLI\Utils\wp_clear_object_cache();
		}

		$progress->finish();

		// Show all info messages
		if ( $show_detail && ! empty( $this->log[ self::LOG_INFO ] ) ) {
			array_map( static function ( $message ): void {
				WP_CLI::log( $message );
			}, $this->log[ self::LOG_INFO ] );
		}

		// Show all warnings
		if ( ! empty( $this->log[ self::LOG_WARNING ] ) ) {
			array_map( static function ( $message ): void {
				WP_CLI::warning( $message );
			}, $this->log[ self::LOG_WARNING ] );
		}

		WP_CLI::log( sprintf(
			__( 'Complete with %d warnings of %d attachments.', 'tribe-storage' ),
			count( $this->log[ self::LOG_WARNING ] ?? [] ),
			count( $query->posts )
		) );

		$this->log = [];
	}

	/**
	 * Returns the local writable target of the resource within the stream.
	 *
	 * @param  string  $path  The URI.
	 *
	 * @return string The path appropriate for use with Flysystem.
	 */
	protected function get_target( string $path = '' ): string {
		$target = substr( $path, strpos( $path, '://' ) + 3 );

		return $target === false ? '' : $target;
	}

	protected function command(): string {
		return 'sync';
	}

	protected function description(): string {
		return __( 'Sync attachments already in the database to to your Cloud Provider', 'tribe-storage' );
	}

	protected function arguments(): array {
		return [
			[
				'type'        => self::POSITIONAL,
				'name'        => self::ARG_SITE_IDS,
				'description' => __( 'Provide a list of blog/site IDs, separated by a space to only process certain sub sites (if on multisite)', 'tribe-storage' ),
				'optional'    => true,
				'repeating'   => true,
			],
			[
				'type'        => self::FLAG,
				'name'        => self::ARG_SHOW_DETAIL,
				'description' => __( 'Show more detail when importing', 'tribe-storage' ),
				'optional'    => true,
			],
			[
				'type'        => self::FLAG,
				'name'        => self::ARG_DRY_RUN,
				'description' => __( 'Do not upload images', 'tribe-storage' ),
				'optional'    => true,
			],
		];
	}

}

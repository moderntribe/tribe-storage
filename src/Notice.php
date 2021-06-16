<?php declare(strict_types=1);

namespace Tribe\Storage;

use Throwable;

/**
 * Build WordPress admin notices.
 *
 * @package Tribe\Storage
 */
class Notice {

	/**
	 * Get an error message if they're using a misconfigured adapter.
	 *
	 * @action tribe/storage/tribe/storage/adapter_error
	 *
	 * @param  \Throwable  $e  The caught exception.
	 *
	 * @return string
	 */
	public function get_adapter_error_notice( Throwable $e ): string {
		if ( ! $this->is_admin_user() ) {
			return '';
		}

		$class = 'notice notice-error';

		return sprintf( '<div class="%1$s"><p>%2$s</p></div>', esc_attr( $class ), esc_html( $e->getMessage() ) );
	}

	/**
	 * Print Notices to the WordPress Dashboard.
	 *
	 * @action admin_notices
	 *
	 * @param  \Throwable  $e  The caught exception.
	 */
	public function print_notices( Throwable $e ): void {
		$notice = $this->get_adapter_error_notice( $e );

		if ( empty( $notice ) ) {
			return;
		}

		echo $notice;
	}

	/**
	 * Check if this user is an admin.
	 *
	 * @return bool
	 */
	protected function is_admin_user(): bool {
		return (bool) current_user_can( 'manage_options' );
	}

}

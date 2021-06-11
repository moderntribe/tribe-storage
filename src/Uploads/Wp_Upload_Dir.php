<?php declare(strict_types=1);

namespace Tribe\Storage\Uploads;

/**
 * Class Wp_Upload_Dir
 *
 * @package Tribe\Storage\Uploads
 */
class Wp_Upload_Dir {

	/**
	 * The original wp_upload_dir() array data indexed by blog_id.
	 *
	 * @var string[][]
	 */
	protected static $original_dir;

	/**
	 * Add a original wp_upload_dir() array to the stack.
	 *
	 * @param  array  $original_dir
	 */
	public function add_dir( array $original_dir ): void {
		$current_blog_id = get_current_blog_id();

		if ( isset( self::$original_dir[ $current_blog_id ] ) ) {
			return;
		}

		self::$original_dir[ $current_blog_id ] = $original_dir;
	}

	/**
	 * Get the original wp_upload_dir() on a per subsite basis.
	 *
	 * @param  int  $blog_id
	 *
	 * @return array
	 */
	public function original_dir( int $blog_id = 1 ): array {
		return self::$original_dir[ $blog_id ] ?? [];
	}

}

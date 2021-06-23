<?php declare(strict_types=1);

namespace Tribe\Storage\Providers;

class Cli_Provider implements Providable {

	/**
	 * An array of commands.
	 *
	 * @var \Tribe\Storage\Cli\Command[]
	 */
	private $commands;

	public function __construct( array $commands ) {
		$this->commands = $commands;
	}

	public function register(): void {
		add_action( 'init', function (): void {
			if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
				return;
			}

			foreach ( $this->commands as $command ) {
				$command->register();
			}
		}, 0, 0 );
	}

}

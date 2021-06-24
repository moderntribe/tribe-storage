<?php declare(strict_types=1);

namespace Tribe\Storage\Cli;

use WP_CLI;
use WP_CLI_Command;

abstract class Command extends WP_CLI_Command {

	public const POSITIONAL = 'positional';
	public const ASSOC      = 'assoc';
	public const FLAG       = 'flag';

	abstract public function run_command( array $args = [], array $assoc_args = [] ): void;

	abstract protected function command(): string;

	abstract protected function description(): string;

	abstract protected function arguments(): array;

	public function __construct() {
		parent::__construct();
	}

	public function register(): void {
		WP_CLI::add_command( 'tribe-storage ' . $this->command(), [ $this, 'run_command' ], [
			'shortdesc' => $this->description(),
			'synopsis'  => $this->arguments(),
		] );
	}

}

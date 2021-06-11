<?php declare(strict_types=1);

namespace Tribe\Storage\Stream_Wrappers\Identity;

/**
 * Class Posix_Identifier
 *
 * Not available on windows by default.
 *
 * @package Tribe\Storage\Stream_Wrappers\Identity
 */
class Posix_Identifier implements Identifier {

	public function uid(): int {
		return posix_getuid();
	}

	public function gid(): int {
		return posix_getgid();
	}

}

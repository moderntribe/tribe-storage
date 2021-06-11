<?php declare(strict_types=1);

namespace Tribe\Storage\Stream_Wrappers;

use BadMethodCallException;
use GuzzleHttp\Psr7\CachingStream;
use GuzzleHttp\Psr7\Utils;
use Jhofm\FlysystemIterator\IteratorException;
use League\Flysystem\AdapterInterface;
use League\Flysystem\FileNotFoundException;
use League\Flysystem\FilesystemInterface;
use League\Flysystem\Util;
use LogicException;
use Psr\Http\Message\StreamInterface;
use RuntimeException;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\Store\FlockStore;
use Throwable;
use Tribe\Storage\Cache\Cache;
use Tribe\Storage\Cache\Lru;
use Tribe\Storage\Stream_Wrappers\Identity\Identifier;

/**
 * A Modern Flysystem Stream Wrapper Implementation.
 *
 * @package Tribe\Storage\Stream_Wrappers
 */
class StreamWrapper {

	public const DEFAULT_PROTOCOL   = 'fly';
	public const PUBLIC_MASK        = 0044;
	public const FILE_WRITABLE_MODE = 33206; // 100666 in octal
	public const FILE_READABLE_MODE = 33060; // 100444 in octal
	public const DIR_WRITABLE_MODE  = 16895; // 40777 in octal
	public const DIR_READABLE_MODE  = 16676; // 40444 in octal
	public const CONFIG_CACHE       = 'cache';
	public const CONFIG_IDENTIFIER  = 'identifier';

	/**
	 * Must be public according to PHP documentation.
	 *
	 * @var resource|null
	 *
	 * @see stream_context_get_options()
	 */
	public $context;

	/**
	 * @var \League\Flysystem\FilesystemInterface
	 */
	protected $filesystem;

	/**
	 * The path to the file on the flysystem.
	 *
	 * @var string
	 */
	protected $path;

	/**
	 * The underlying stream resource.
	 *
	 * @var \Psr\Http\Message\StreamInterface
	 */
	protected $stream;

	/**
	 * A directory listing.
	 *
	 * @var \Jhofm\FlysystemIterator\FilesystemIterator
	 */
	protected $listing;

	/**
	 * Stream context options.
	 *
	 * @var string[]
	 */
	protected $options = [];

	/**
	 * Manage the state of when the stream should be flushed.
	 *
	 * @var bool
	 */
	protected $flush = false;

	/**
	 * The file access mode when the stream is opened.
	 *
	 * @var string
	 */
	protected $mode;

	/**
	 * @var \Tribe\Storage\Cache\Cache
	 */
	protected $cache;

	/**
	 * @var \Tribe\Storage\Stream_Wrappers\Identity\Identifier
	 */
	protected $identifier;

	/**
	 * Store the number of bytes to buffer if stream_set_write_buffer()
	 * is called.
	 *
	 * @var int
	 */
	protected $buffer;

	/**
	 * The number of bytes that have been written since the last flush.
	 *
	 * @var int
	 */
	protected $bytes_written = 0;

	/**
	 * Store a Symfony Lock instance.
	 *
	 * @var \Symfony\Component\Lock\LockInterface
	 */
	protected $lock;

	/**
	 * The registered filesystems.
	 *
	 * @var \League\Flysystem\FilesystemInterface[]
	 */
	protected static $filesystems = [];

	/**
	 * The protocol, e.g fly.
	 *
	 * @var string
	 */
	protected static $protocol;

	/**
	 * Stores configuration options.
	 *
	 * @var Mixed[]
	 */
	protected static $config = [];

	/**
	 * Called after the wrapper is initialized e.g. by fopen()/file_get_contents() etc...
	 *
	 * @param  string|null  $path The file path or URI.
	 * @param  string|null  $mode The mode used to open the file, see: fopen().
	 * @param  int|null     $options Holds additional flags set the by streams API.
	 * @param  string|null  $opened_path If $path was opened, we should use this path instead.
	 *
	 * @return bool
	 *
	 * @throws \League\Flysystem\FileNotFoundException
	 */
	public function stream_open( ?string $path, ?string $mode, ?int $options, ?string &$opened_path ): bool {
		$this->path = $path;
		$target     = $this->get_target();

		// strip off 'b' or 't' from the mode
		$this->mode = rtrim( $mode, 'bt' );

		switch ( $this->mode ) {
			case 'r':
				$resource = $this->filesystem()->readStream( $target );
				break;
			case 'a':
				try {
					$resource = $this->filesystem()->readStream( $target );
				} catch ( FileNotFoundException $e ) {
					$this->flush = true;
					$resource    = fopen( 'php://temp', 'w+b' );
				}
				break;
			case 'w':
			default:
				$resource = fopen( 'php://temp', 'w+b' );
		}

		if ( ! is_resource( $resource ) ) {
			throw new RuntimeException( 'Could not assign a resource' );
		}

		// Create an instance of a StreamInterface
		$this->stream = Utils::streamFor( $resource );

		// Some streams are not seekable, cache them to convert them to a seekable
		// stream when the API requests it.
		if ( ! $this->stream->isSeekable() && ($options & STREAM_MUST_SEEK) ) {
			$this->stream = new CachingStream( $this->stream );
		}

		if ( $options & STREAM_USE_PATH ) {
			$opened_path = $path;
		}

		return $this->stream instanceof StreamInterface;
	}

	/**
	 * Tests for end of file.
	 *
	 * @return bool
	 */
	public function stream_eof(): bool {
		return $this->stream->eof();
	}

	/**
	 * Read from the stream.
	 *
	 * @param  int  $count How many bytes from the current position should be returned
	 *
	 * @return string
	 */
	public function stream_read( int $count = 0 ): string {
		return $this->stream->read( $count );
	}

	/**
	 * Seek to a specific location in the stream.
	 *
	 * @param  int  $offset The stream offset to seek to.
	 * @param  int  $whence Constants: SEEK_SET, SEEK_CUR, SEEK_END
	 *
	 * @return bool
	 */
	public function stream_seek( int $offset, int $whence = SEEK_SET ): bool {
		if ( ! $this->stream->isSeekable() ) {
			return false;
		}

		$this->stream->seek( $offset, $whence );

		return true;
	}

	/**
	 * Retrieve the current position of the stream.
	 *
	 * @see fseek()
	 *
	 * @return int
	 */
	public function stream_tell(): int {
		return $this->stream->tell();
	}

	/**
	 * Write to the stream.
	 *
	 * @see https://bugs.php.net/bug.php?id=53328
	 *
	 * @param  string  $data The data to write.
	 *
	 * @return int
	 */
	public function stream_write( string $data ): int {

		if ( ! $this->stream->isWritable() ) {
			return 0;
		}

		$this->flush = true;

		// Enforce append semantics
		if ( 'a' === $this->mode && $this->stream->isSeekable() ) {
			$this->stream->seek( 0, SEEK_END );
		}

		$bytes                = $this->stream->write( $data );
		$this->bytes_written += $bytes;

		// Enforce our own flushing if the API requested a buffer
		if ( isset( $this->buffer ) && $this->bytes_written >= $this->buffer ) {
			$this->stream_flush();
		}

		return $bytes;
	}

	/**
	 * Delete a file.
	 *
	 * @param  string  $path
	 *
	 * @return bool
	 */
	public function unlink( string $path ): bool {
		$this->path = $path;

		try {
			$this->cache()->remove( $this->get_target() );

			return $this->filesystem()->delete( $this->get_target( $path ) );
		} catch ( FileNotFoundException $e ) {
			return $this->trigger_error( $e->getMessage() );
		}
	}

	/**
	 * Open a directory handle.
	 *
	 * @param  string  $path
	 * @param  int     $options
	 *
	 * @return bool
	 */
	public function dir_opendir( string $path, int $options ): bool {
		$this->path = $path;

		$path = Util::normalizePath( $this->get_target() );

		// Get the contents of a directory from flysystem using the Iterator plugin
		$this->listing = $this->filesystem()->createIterator( [ 'recursive' => false ], $path );

		if ( ! $this->listing->valid() ) {
			return false;
		}

		$this->listing->rewind();

		return true;
	}

	/**
	 * Read an entry from the directory handle.
	 *
	 * @return string|false
	 */
	public function dir_readdir() {
		try {
			$current = $this->listing->current();
		} catch ( IteratorException $e ) {
			return $this->trigger_error( $e->getMessage() );
		}

		if ( $current ) {
			$this->listing->next();

			return $current['basename'] ?? false;
		}

		return false;
	}

	/**
	 * Close the directory handle.
	 *
	 * @return bool
	 */
	public function dir_closedir(): bool {
		unset( $this->listing );

		// Force garbage collection
		gc_collect_cycles();

		return true;
	}

	/**
	 * Rewind the directory handle.
	 *
	 * @return bool
	 */
	public function dir_rewinddir(): bool {
		$this->listing->rewind();

		return true;
	}

	/**
	 * Create a directory.
	 *
	 * @param  string  $path     The path to create.
	 * @param  int     $mode     The passed to mkdir()
	 * @param  int     $options  A bitwise mask of values, such as STREAM_MKDIR_RECURSIVE.
	 *
	 * @return bool
	 *
	 * @throws \League\Flysystem\FileNotFoundException
	 */
	public function mkdir( string $path, int $mode, int $options ): bool {
		$this->path = $path;
		$target     = $this->get_target();
		$this->cache()->remove( $target );

		// If recursive, or a single level directory, just create it.
		if ( ($options & STREAM_MKDIR_RECURSIVE) || false === strpos( $target, '/' ) ) {
			return $this->filesystem()->createDir( $target );
		}

		if ( ! $this->filesystem()->getAdapter()->has( dirname( $target ) ) ) {
			throw new FileNotFoundException( $target );
		}

		return $this->filesystem()->createDir( $target );
	}

	/**
	 * Renames a file or directory.
	 *
	 * @param  string  $path_from
	 * @param  string  $path_to
	 *
	 * @return bool
	 */
	public function rename( string $path_from, string $path_to ): bool {
		$this->path = $path_from;

		$from = Util::normalizePath( $this->get_target( $path_from ) );
		$to   = Util::normalizePath( $this->get_target( $path_to ) );

		$this->cache()->remove( $from );
		$this->cache()->remove( $to );

		// Don't needlessly rename anything
		if ( $from === $to ) {
			return true;
		}

		try {
			return $this->filesystem()->forceRename( $from, $to );
		} catch ( BadMethodCallException $e ) {
			return $this->filesystem()->rename( $from, $to );
		} catch ( Throwable $e ) {
			return $this->trigger_error( $e->getMessage() );
		}
	}

	/**
	 * Removes a directory.
	 *
	 * @param  string  $path
	 * @param  int     $options
	 *
	 * @return bool
	 */
	public function rmdir( string $path, int $options ): bool {
		$this->path = $path;
		$target     = $this->get_target();

		$directory = Util::normalizePath( $target );

		if ( '' === $target ) {
			$this->trigger_error( 'Root directories cannot be deleted.' );

			return false;
		}

		if ( $options & STREAM_MKDIR_RECURSIVE ) {
			return $this->filesystem()->deleteDir( $directory );
		}

		$contents = $this->filesystem()->listContents( $directory );

		if ( ! empty( $contents ) ) {
			$this->trigger_error( "$directory is not empty" );

			return false;
		}

		return $this->filesystem()->deleteDir( $directory );
	}

	/**
	 * @param  int  $cast_as
	 *
	 * @return resource|false
	 */
	public function stream_cast( int $cast_as ) {
		$stream = clone( $this->stream );

		return $stream->detach();
	}

	/**
	 * Close the stream.
	 */
	public function stream_close(): void {
		$this->stream_flush();

		if ( ! $this->stream ) {
			return;
		}

		$this->stream->close();
		unset( $this->stream, $this->cache );
	}

	/**
	 * Flush the stream.
	 *
	 * Called in response to fflush() and our manually managed state.
	 *
	 * @return bool
	 */
	public function stream_flush(): bool {
		if ( ! $this->flush ) {
			return true;
		}

		if ( 'r' === $this->mode ) {
			return true;
		}

		$this->flush = false;

		if ( $this->stream->isSeekable() ) {
			$this->stream->rewind();
		}

		$this->cache()->remove( $this->get_target() );

		// Detach the stream to get the underlying resource and pass to flysystem for writing.
		return $this->filesystem()->putStream( $this->get_target(), $this->stream->detach() );
	}

	/**
	 * Retrieve information about a resource.
	 *
	 * @see fstat(), stat()
	 *
	 * @return int[]
	 */
	public function stream_stat(): array {
		$target = $this->get_target();
		$cache  = $this->cache()->get( $target );

		if ( $cache ) {
			return $cache;
		}

		$stat = $this->stat_template();

		// Set the file/directory mode based on the stream's mode.
		$stat[2] = $stat['mode'] = $this->stream->isWritable() ? self::FILE_WRITABLE_MODE : self::FILE_READABLE_MODE;

		try {
			// Try to get the size from flysystem, if it exists.
			$size    = $this->filesystem()->getSize( $target );
			$stat[7] = $stat['size'] = (int) $size;

			$this->cache()->set( $target, $stat );

			return $stat;
		} catch ( Throwable $e ) {
			// Newly created file, try to use stream size as the file size.
			$size = $this->stream->getSize() ?? 0;

			if ( $size ) {
				$stat[7] = $stat['size'] = $size;
			} else {
				$stat['size'] = $stat[7];
			}

			return $stat;
		}
	}

	/**
	 * Advisory file locking.
	 *
	 * Use Symfony's Lock Component to manage locking.
	 *
	 * @param  int  $operation
	 *
	 * @return bool
	 *
	 * @see flock()
	 */
	public function stream_lock( int $operation ): bool {
		if ( in_array( $operation, [ LOCK_SH, LOCK_EX, LOCK_UN, LOCK_NB ] ) ) {
			if ( ! isset( $this->lock ) ) {
				$lockPath   = sys_get_temp_dir() . '/flysystem-wrapper';
				$factory    = new LockFactory( new FlockStore( $lockPath ) );
				$this->lock = $factory->createLock( $this->get_target() );
			}

			// Locks are released automatically, but in case the API asks
			if ( ($operation & LOCK_UN) === LOCK_UN ) {
				return $this->lock->release();
			}

			return $this->lock->acquire();
		}

		return true;
	}

	/**
	 * Truncate the stream.
	 *
	 * @param  int  $new_size
	 *
	 * @return bool
	 */
	public function stream_truncate( int $new_size ): bool {
		if ( ! $this->stream->isWritable() ) {
			return false;
		}

		$this->flush = true;

		return ftruncate( $this->stream->detach(), $new_size );
	}

	/**
	 * Change stream metadata.
	 *
	 * @param  string  $path
	 * @param  int     $option
	 * @param  mixed   $value
	 *
	 * @return bool
	 *
	 * @throws \League\Flysystem\FileExistsException
	 */
	public function stream_metadata( string $path, int $option, $value ): bool {
		$this->path = $path;

		switch ( $option ) {
			case STREAM_META_ACCESS:
				$permissions = octdec( substr( decoct( $value ), - 4 ) );
				$is_public   = $permissions & self::PUBLIC_MASK;
				$visibility  = $is_public ? AdapterInterface::VISIBILITY_PUBLIC : AdapterInterface::VISIBILITY_PRIVATE;

				try {
					return $this->filesystem()->setVisibility( $this->get_target(), $visibility );
				} catch ( LogicException $e ) {
					// The adapter doesn't support visibility
				} catch ( Throwable $e ) {
					// Something else went wrong
					return $this->trigger_error( $e->getMessage() );
				}

				return true;

			case STREAM_META_TOUCH:
				$normalized_path = Util::normalizePath( $this->get_target() );

				if ( $this->filesystem()->has( $normalized_path ) ) {
					return true;
				}

				return $this->filesystem()->write( $normalized_path, '' );

			default:
				return false;
		}
	}

	/**
	 * Retrieve information about a file.
	 *
	 * Called in response to many core PHP file functions, e.g.
	 * copy(), stat(), file_exists(), filesize() etc...
	 *
	 * @param  string  $path
	 * @param  int     $flags
	 *
	 * @return array|false
	 */
	public function url_stat( string $path, int $flags ) {
		$this->path = $path;
		$cache      = $this->cache()->get( $this->get_target() );

		if ( $cache ) {
			return $cache;
		}

		$stat = $this->stat_template();

		try {
			// Try to get our information from flysystem
			$meta = $this->filesystem()->getMetadata( $this->get_target() );

			// Even if this worked, it could be empty
			if ( empty( $meta ) ) {
				return $this->stat_template();
			}

			$type = $meta['type'] ?? '';

			if ( 'dir' === $type ) {
				// Provide a default templates for directories
				$stat[2] = $stat['mode'] = self::DIR_WRITABLE_MODE;
				$stat[4] = $stat['uid'] = $this->identifier()->uid();
				$stat[5] = $stat['gid'] = $this->identifier()->gid();

				$this->cache()->set( $this->get_target(), $stat );

				return $stat;
			}

			$size = $meta['size'] ?? $this->filesystem()->getSize( $this->get_target() );

			if ( $size ) {
				$stat[7] = $stat['size'] = (int) $size;
			} else {
				$stat['size'] = $stat[7];
			}

			$stat[2] = $stat['mode']  = self::FILE_WRITABLE_MODE;
			$stat[4] = $stat['uid']   = $this->identifier()->uid();
			$stat[5] = $stat['gid']   = $this->identifier()->gid();
			$stat[8] = $stat['atime'] = time();

			if ( ! empty( $meta['timestamp'] ) ) {
				$stat[9]  = $stat['mtime'] = $meta['timestamp'];
				$stat[10] = $stat['ctime'] = $meta['timestamp'];
			}

			$this->cache()->set( $this->get_target(), $stat );

			return $stat;
		} catch ( FileNotFoundException $e ) {
			// The API will request a file and not expect it to exist
			if ( ! ($flags & STREAM_URL_STAT_QUIET) ) {
				return $this->trigger_error( $e->getMessage() );
			}
		} catch ( Throwable $e ) {
			return $this->trigger_error( $e->getMessage() );
		}

		return false;
	}

	/**
	 * Changes stream options.
	 *
	 * @param  int  $option
	 * @param  int  $arg1
	 * @param  int  $arg2
	 *
	 * @return bool True on success, false on failure.
	 */
	public function stream_set_option( int $option, int $arg1, int $arg2 ): bool {
		switch ( $option ) {
			case STREAM_OPTION_BLOCKING:
				// This works for the local adapter. It doesn't do anything for
				// memory streams.
				return stream_set_blocking( $this->stream->detach(), $arg1 );

			case STREAM_OPTION_READ_TIMEOUT:
				return stream_set_timeout( $this->stream->detach(), $arg1, $arg2 );

			case STREAM_OPTION_READ_BUFFER:
				if ( $arg1 === STREAM_BUFFER_NONE ) {
					return stream_set_read_buffer( $this->stream->detach(), 0 ) === 0;
				}

				return stream_set_read_buffer( $this->stream->detach(), $arg2 ) === 0;

			case STREAM_OPTION_WRITE_BUFFER:
				$this->buffer = $arg1 === STREAM_BUFFER_NONE ? 0 : $arg2;

				return true;
		}

		return false;
	}

	/**
	 * Register the `fly://` stream wrapper
	 *
	 * @param  \League\Flysystem\FilesystemInterface               $filesystem
	 * @param  \Tribe\Storage\Stream_Wrappers\Identity\Identifier  $identifier
	 * @param  \Tribe\Storage\Cache\Cache|null                     $cache
	 * @param  string                                              $protocol
	 * @param  array                                               $config
	 *
	 * @return bool
	 */
	public static function register( FilesystemInterface $filesystem, Identifier $identifier, ?Cache $cache = null, string $protocol = '', array $config = [] ): bool {
		$protocol = $protocol ?: self::DEFAULT_PROTOCOL;

		if ( static::registered( $protocol ) ) {
			return false;
		}

		static::$config[ $protocol ]                            = $config;
		static::$config[ $protocol ][ self::CONFIG_CACHE ]      = $cache ?: new Lru();
		static::$config[ $protocol ][ self::CONFIG_IDENTIFIER ] = $identifier;
		static::$filesystems[ $protocol ]                       = $filesystem;
		static::$protocol                                       = $protocol;

		return stream_wrapper_register( $protocol, StreamWrapper::class, STREAM_IS_URL );
	}

	public static function unregister( ?string $protocol = null ): bool {
		$protocol = $protocol ?: self::DEFAULT_PROTOCOL;

		if ( ! static::registered( $protocol ) ) {
			return false;
		}

		unset( static::$filesystems[ $protocol ] );
		unset( static::$config[ $protocol ] );

		return stream_wrapper_unregister( $protocol );
	}

	/**
	 * Wrap our stream in a seekable Caching Stream.
	 *
	 * @param resource $stream
	 *
	 * @return \GuzzleHttp\Psr7\CachingStream
	 */
	protected function cache_stream( $stream ): CachingStream {
		return new CachingStream( Utils::streamFor( $stream ) );
	}

	/**
	 * Returns the local writable target of the resource within the stream.
	 *
	 * @param  string  $path  The URI.
	 *
	 * @return string The path appropriate for use with Flysystem.
	 */
	protected function get_target( string $path = '' ): string {
		$path = $path ?: $this->path;

		$target = substr( $path, strpos( $path, '://' ) + 3 );

		return $target === false ? '' : $target;
	}

	/**
	 * Get the instance of the Flysystem Filesystem.
	 *
	 * @return \League\Flysystem\FilesystemInterface
	 */
	protected function filesystem(): FilesystemInterface {
		if ( isset( $this->filesystem ) ) {
			return $this->filesystem;
		}

		$this->filesystem = static::$filesystems[ $this->get_protocol() ];

		return $this->filesystem;
	}

	/**
	 * Get the protocol from the path.
	 *
	 * @return string
	 */
	protected function get_protocol(): string {
		return substr( $this->path, 0, strpos( $this->path, '://' ) );
	}

	/**
	 * Trigger an error, always return false.
	 *
	 * @param  string  $error
	 *
	 * @return bool
	 */
	protected function trigger_error( string $error ): bool {
		trigger_error( $error, E_USER_WARNING );

		return false;
	}

	/**
	 * Get the in memory cache instance.
	 *
	 * @return \Tribe\Storage\Cache\Cache
	 */
	protected function cache(): Cache {
		if ( ! $this->cache ) {
			$this->cache = static::$config[ static::$protocol ][ self::CONFIG_CACHE ] ?: new Lru();
		}

		return $this->cache;
	}

	/**
	 * Get the identifier instance.
	 *
	 * @return \Tribe\Storage\Stream_Wrappers\Identity\Identifier
	 */
	protected function identifier(): Identifier {
		if ( ! $this->identifier ) {
			$this->identifier = static::$config[ static::$protocol ][ self::CONFIG_IDENTIFIER ];
		}

		return $this->identifier;
	}

	/**
	 * Whether this wrapper is registered.
	 *
	 * @param  string  $protocol
	 *
	 * @return bool
	 */
	protected static function registered( string $protocol = '' ): bool {
		return in_array( $protocol, stream_get_wrappers(), true );
	}

	/**
	 * Gets a URL stat template with default values.
	 *
	 * @see stat()
	 *
	 * @return array
	 */
	private function stat_template(): array {
		// phpcs:disable
		return [
			0  => 0,  'dev'     => 0,
			1  => 0,  'ino'     => 0,
			2  => 0,  'mode'    => 0,
			3  => 0,  'nlink'   => 0,
			4  => 0,  'uid'     => 0,
			5  => 0,  'gid'     => 0,
			6  => -1, 'rdev'    => -1,
			7  => 0,  'size'    => 0,
			8  => 0,  'atime'   => 0,
			9  => 0,  'mtime'   => 0,
			10 => 0,  'ctime'   => 0,
			11 => -1, 'blksize' => -1,
			12 => -1, 'blocks'  => -1,
		];
		// phpcs:enable
	}

}

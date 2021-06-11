<?php declare(strict_types=1);

namespace Tribe\Storage\Adapters;

use Aws\S3\S3Client;
use League\Flysystem\AdapterInterface;
use League\Flysystem\AwsS3v3\AwsS3Adapter;

/**
 * The S3 Flysystem bridge adapter.
 *
 * @package Tribe\Storage\Adapters
 */
class S3_Adapter extends Adapter {

	/**
	 * The S3 bucket name.
	 *
	 * @var string
	 */
	protected $bucket;

	/**
	 * @var \Aws\S3\S3Client
	 */
	protected $client;

	/**
	 * S3_Adapter constructor.
	 *
	 * @param  string            $bucket
	 * @param  \Aws\S3\S3Client  $client
	 */
	public function __construct( string $bucket, S3Client $client ) {
		$this->bucket = $bucket;
		$this->client = $client;
	}

	public function get(): AdapterInterface {
		return new AwsS3Adapter( $this->client, $this->bucket );
	}

}

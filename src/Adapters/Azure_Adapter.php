<?php declare(strict_types=1);

namespace Tribe\Storage\Adapters;

use League\Flysystem\AdapterInterface;
use League\Flysystem\AzureBlobStorage\AzureBlobStorageAdapter;
use MicrosoftAzure\Storage\Blob\BlobRestProxy;

/**
 * Class AzureAdapter
 *
 * @package Tribe\Storage\Adapters
 */
class Azure_Adapter extends Adapter {

	/**
	 * The Azure account name.
	 *
	 * @var string
	 */
	protected $account_name;

	/**
	 * The Azure secret key.
	 *
	 * @var string
	 */
	protected $account_key;

	/**
	 * The Azure container name.
	 *
	 * @var string
	 */
	protected $azure_container;

	/**
	 * Azure Blob Service options
	 *
	 * @see http://docs.guzzlephp.org/en/latest/request-options.html
	 *
	 * @var mixed[]
	 */
	protected $options;

	/**
	 * Azure_Adapter constructor.
	 *
	 * @param  string  $account_name     The Azure account name.
	 * @param  string  $account_key      The Azure secret key.
	 * @param  string  $azure_container  The Azure container name.
	 * @param  array   $options          The blob service Guzzle options
	 */
	public function __construct( string $account_name, string $account_key, string $azure_container, array $options = [] ) {
		$this->account_name    = $account_name;
		$this->account_key     = $account_key;
		$this->azure_container = $azure_container;
		$this->options         = $options;
	}

	/**
	 * Get the Adapter.
	 *
	 * @return \League\Flysystem\AdapterInterface
	 */
	public function get(): AdapterInterface {
		$connection_string = sprintf(
			'DefaultEndpointsProtocol=https;AccountName=%s;AccountKey=%s;',
			$this->account_name,
			$this->account_key
		);

		$client = BlobRestProxy::createBlobService( $connection_string, $this->options );

		return new AzureBlobStorageAdapter( $client, $this->azure_container );
	}

}

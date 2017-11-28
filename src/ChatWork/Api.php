<?php
namespace App\ChatWork;

use Cake\Http\Client;
use Cake\Log\LogTrait;
use Psr\Log\LogLevel;

/**
 * ChatWork API.
 * @see http://developer.chatwork.com/ja/
 */
class Api
{
	use LogTrait;
	
	const API_VERSION = 'v2';

	/**
	 * HttpOptions.
	 * 
	 * @var array
	 */
	private $httpOptions = [
			'timeout' => 60,
			'headers' => [
					'User-Agent' => 'hira8-sorairo-lunch bot beta(+http://hira88.hira8.jp/sorairo-lunch/)',
					'Accept' => 'application/json',
			]
	];
		
	/**
	 * CHATWORK API BASE URI
	 * 
	 * @var string
	 */
	private $baseUri = 'https://api.chatwork.com/';
	
	/**
	 * CHATWORK API TOKEN
	 * set "X-ChatWorkToken" Header.
	 *
	 * @var string
	 */
	public $apiToken;
	
	public function __construct(String $apiToken){
		$this->apiToken = $apiToken;
		$httpOptions = [
				'headers' => [
						'X-ChatWorkToken' => $this->apiToken
				]
		];
		$this->httpOptions = array_merge_recursive($this->httpOptions, $httpOptions);
	}
	
	/**
	 * （グループ）チャットにメッセージをポストします。
	 * 
	 * @param String $roomId
	 * @param String $message
	 * @return boolean
	 */
	public function postRoomsMessages(String $roomId, String $message){
		$client = new Client($this->httpOptions);
		$url = $this->baseUri . self::API_VERSION . '/rooms/' . $roomId . '/messages';
		$response = $client->post($url, http_build_query(['body' => $message]));
		if ($response->isOk()) {
			return true;
		} else {
			$this->log('Response Error: [code: ' . $response->getStatusCode() . '] ' . $response->getReasonPhrase() . "\n    " . $url, LogLevel::ERROR);
			return false;
		}
	}
    
}

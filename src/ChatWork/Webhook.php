<?php
namespace App\ChatWork;

// use PhpParser\Node\Scalar\String_;
use \Cake\Http\ServerRequest;
use Cake\Log\LogTrait;
use Psr\Log\LogLevel;

/**
 * ChatWork Webhook.
 * 
 * @see http://developer.chatwork.com/ja/
 */
class Webhook
{

	use LogTrait;
	
	/**
	 * CHATWORK WEBHOOK TOKEN
	 * 
	 * @var string
	 */
	public $webhookToken;
	
	/**
	 * ServerRequest
	 *
	 * @var \Cake\Http\ServerRequest
	 */
	public $request;
	
	/**
	 * httpd Header X-ChatWorkWebhookSignature
	 *
	 * @var string
	 */
	public $signature;
	
	/**
	 * Httpd POST Raw Body 
	 *
	 * @var string
	 */
	public $rawBody;
	
	public function __construct(String $webhookToken, ServerRequest $request){
		$this->webhookToken = $webhookToken;
		$this->request = $request;
		$this->signature = $this->request->getHeaderLine('X-ChatWorkWebhookSignature');
		$this->rawBody = $this->request->input();
	}
    
    /**
     * POSTのボディーをsha256でチェック
     * 
     * @param String $signature
     * @param String $raw_body
     * @return boolean
     */
    public function checkSignature(){
    	$decodeToken = base64_decode($this->webhookToken);
    	$hashed = base64_encode(hash_hmac('sha256', $this->rawBody, $decodeToken, true));
    	if ($hashed == $this->signature) {
    		return true;
    	} else {
    		$this->log('Signature Check Error: Signature:' . $this->signature . ' vs Hashed:' . $hashed . "\n" . $webhook->rawBody, LogLevel::ERROR);
    		return false;
    	}
    }
}

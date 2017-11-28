<?php
namespace App\Controller;

use App\ChatWork\Webhook;
use App\ChatWork\Api;
use Cake\ORM\TableRegistry;
use Psr\Log\LogLevel;

/**
 * ChatworkWebhook Controller
 *
 * @property \App\Model\Table\ItemsTable $Items
 * @property \App\Model\Table\OrdersTable $Orders
 *
 * @method \App\Model\Entity\Item[] paginate($object = null, array $settings = [])
 */
class ChatworkWebhookController extends AppController
{
	
	public function __construct($request, $response) {
		parent::__construct($request, $response);
		$this->autoRender = false;
		
		$this->Items = TableRegistry::get('Items');
		$this->Orders= TableRegistry::get('Orders');
	}
	
    /**
     * ChatworkからのWebhook
     * 
     */
    public function hook(){
    	$this->log('Hook Access: ', LogLevel::WARNING);
    	
    	// メニューを取得
    	$query = $this->Items->find('ActiveMenu')->find('list');
    	$query->enableHydration(false); // 結果をArrayで
    	$menu = $query->toArray();
    	
    	// シグネチャーをチェック
    	$webhook = new Webhook(env('CHATWORK_WEBHOOK_TOKEN'), $this->request);
    	if ( $webhook->checkSignature()) {
    		echo "OK.\n";
    	} else {
    		echo "NG.\n";
    		$this->log('Hook Access: Signature Check Error: ' . $webhook->signature . "\n" . $webhook->rawBody, LogLevel::ERROR);
    		return;
    	}
    	
    	// POST Body from Chatwork (Array not JSON)
    	$body = $this->request->getData();
     	$message_id = $body['webhook_event']['message_id'];
    	$account_id = $body['webhook_event']['account_id'];
    	$text = $body['webhook_event']['body'];
    	
    	// TEXTを解析
    	// TODO: Refactoring! Refactoring! Refactoring!
    	// 「注文」で始まる： その後の各行を注文商品とする
    	// 「メニュー」で始まる：メニューを返す
    	// 「キャンセル」で始まる：$account_idの注文を取り消す
    	$roomId = env('CHATWORK_ROOM_ID');
    	$message  = '[rp aid=' . $account_id. ' to=' . $roomId. '-' . $message_id. ']'; // [rp aid={account_id} to={room_id}-{message_id}]
    	
    	if (preg_match('/^注文/', $text)) {
    		$message .= " ありがとうございます、下記の通り受け付けました。\n";
    		$message .= "$text";
    		
    	} elseif (preg_match('/^メニュー/', $text)) {
    		$message .= " メニューだよ。\n";
    		
    	} elseif (preg_match('/^キャンセル/', $text)) {
    		$message .= " キャンセルだよ。\n";
    		
    	} else {
    		// nothing to do.
    	}
    	
    	// CHATWORK APIでリプ
    	// TOKEN発行者の発言になる
    	$api = new Api(env('CHATWORK_API_TOKEN'));
    	$api->postRoomsMessages($roomId, $message);
    	
    	
    }
}

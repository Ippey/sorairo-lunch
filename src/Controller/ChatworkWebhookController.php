<?php
namespace App\Controller;

use App\ChatWork\Webhook;
use App\ChatWork\Api;
use Cake\ORM\TableRegistry;
use Psr\Log\LogLevel;
use PhpParser\Node\Stmt\TryCatch;

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
	/**
	 * 注文の区切り文字
	 * 
	 * @var string
	 */
	const ORDER_SEP = "\n";
	
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
    	$query = $this->Items->find('ActiveMenu');// ->find('list');
    	$query->enableHydration(false); // 結果をArrayで
    	$menu = $query->toArray(); // 全情報
    	
    	// チェック向けに商品名のみの配列を準備
    	$menuNames = [];
    	foreach ($menu as $item) {
    		$menuNames[$item['name']] = $item['id'];
    	}

    	// シグネチャーをチェック
    	$webhook = new Webhook(env('CHATWORK_WEBHOOK_TOKEN'), $this->request);
    	if ( $webhook->checkSignature()) {
    		echo "Check OK.\n";
    	} else {
    		echo "NG.\n";
    		return;
    	}
    	
    	// POST Body from Chatwork (Array not JSON)
    	$body = $this->request->getData();
     	$message_id = $body['webhook_event']['message_id'];
    	$account_id = $body['webhook_event']['account_id'];
    	$text = $body['webhook_event']['body'];
    	
    	$today = date('Y-m-d');
    	
    	// TEXTを解析
    	// TODO: Controller HEAVY Refactoring! Refactoring! Refactoring!
    	// 「注文」で始まる： その後の各行を注文商品とする
    	// 「メニュー」で始まる：メニューを返す
    	// 「キャンセル」で始まる：$account_idの注文を取り消す
    	$roomId = env('CHATWORK_ROOM_ID');
    	$message  = '';
    	$message .= '[rp aid=' . $account_id. ' to=' . $roomId. '-' . $message_id. ']'; // [rp aid={account_id} to={room_id}-{message_id}]
    	$message .= "[piconname:" . $account_id. "] さんへ\n";
    	// $message .= " 〜 おにぎりボットがお知らせします 8-)\n";

    	// 既存の注文を調べる
    	$query = $this->Orders->find();
    	// $query->enableHydration(false); // 結果をArrayで
    	$query->contain(['OrderItems.Items']);
    	$query->where(['chatwork_account' => $account_id, 'order_date' => $today]);
    	
    	
    	if (preg_match('/^注文(.*)/s', $text, $matches)) {

    		// 商品名をIDに変換　ない商品は無視されます。
    		$order_items = explode(self::ORDER_SEP, $matches[1]);
    		$new_order_items= []; // 保存用にエンティティを生成
    		foreach ($order_items as $order_item) {
    			if (array_key_exists($order_item, $menuNames)) {
    				$entity = $this->Orders->OrderItems->newEntity();
    				$entity->item_id = $menuNames[$order_item];
    				$entity->number = 1;
    				$new_order_items[] = $entity;
    			}
    		}
    		if (0 == count($new_order_items)) {
    			$message .= "(sweat) 商品が見つかりません。 (sweat)";
    		} else {
    			// すでに注文がある場合は削除＆登録
    			if (0 <> $query->count()) {
    				$row = $query->first();
    				$entity = $this->Orders->get($row->id);
    				$result = $this->Orders->delete($entity);
    			}
    			
    			// 登録
    			$new_order = $this->Orders->newEntity();
    			$new_order->chatwork_account = $account_id;
    			$new_order->order_date= $today;
    			$new_order->order_items = $new_order_items;
    			$this->Orders->save($new_order);
    			
    			$message .= " ありがとうございます、下記の通り受け付けました :) \n\n";
    			// クエリ
    			$newQuery= $query->cleanCopy();
    			$row = $newQuery->first();
    			$total = 0;
    			foreach ($row->order_items as $item) {
    				$message .= "　・" . $item->item->name . " (" . $item->item->unit_price. "円)\n";
    				$total += $item->item->unit_price;
    			}
    			$message .= "\n合計: " . $total. "円\n";
    			$message .= "(注文番号: " . $row->id . ")\n";
    		}
    		
    	} elseif (preg_match('/^メニュー/', $text)) {
    		$message .= " メニューだよ。\n[code]";
    		foreach ($menu as $item) {
    			$message .= "　・" . $item['name'] . " (" . $item['unit_price'] . "円)\n";
    		}
    		$message .= "[/code]";
    		
    	} elseif (preg_match('/^キャンセル/', $text)) {
    		$message .= " キャンセルだよ。\n";
    		
    	} else {
    		// not post Messages
    		return;
    	}
    	
    	// CHATWORK APIでリプ
    	// TOKEN発行者の発言になる
    	$api = new Api(env('CHATWORK_API_TOKEN'));
    	$api->postRoomsMessages($roomId, $message);
    	
    	
    }
}

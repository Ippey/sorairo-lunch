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
	/**
	 * 注文の区切り文字
	 * 
	 * @var string
	 */
	const ORDER_SEP = "\n";
  
  /*
   * リクエストモデル
   */
  private $reqmodel = [];
	
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

  	// シグネチャーをチェック
  	$webhook = new Webhook(env('CHATWORK_WEBHOOK_TOKEN'), $this->request);
  	if ( $webhook->checkSignature()) {
  		echo "Check OK.\n";
  	} else {
  		echo "Check NG.\n";
  		return;
  	}
  	
    if( get_cw_request() ) {
    	// メニューを取得
    	$query = $this->Items->find('ActiveMenu');// ->find('list');
    	$query->enableHydration(false); // 結果をArrayで
    	$menu = $query->toArray(); // 全情報
    	
    	// チェック向けに商品名のみの配列を準備
    	$menuNames = [];
    	foreach ($menu as $item) {
    		$menuNames[$item['name']] = $item['id'];
    	}
    	
    	// 共通条件
    	$today = date('Y-m-d');
    	$roomId = env('CHATWORK_ROOM_ID');
    	
    	// CHATWORK APIでリプ
    	// TOKEN発行者の発言になる
    	$api = new Api(env('CHATWORK_API_TOKEN'));
      
      if ($reqmodel["command"] != "集計") {
        // 既存の注文を調べる
        $query = $this->Orders->find();
        // $query->enableHydration(false); // 結果をArrayで
        $query->contain(['OrderItems.Items']);
        $query->where(['chatwork_account' => $account_id, 'order_date' => $today]);
      }
      
      //コマンドによる処理分岐
      switch ($reqmodel) {
        case "集計":
          $staffs = env('HIRA8_STAFF_IDS');
      		$staffIds = explode(',', $staffs);
      		$to = [];
      		foreach ($staffIds as $id) {
      			$to[] = "[To:" . $id . "][piconname:" . $id . "] さん";
      		}
      		$message  = join('、', $to) . "\n\n";
      		$message .= $today . "の集計です(F)\n\n[hr](*)全ての注文\n";
      		
      		// 集計（合計）
      		$query = $this->Orders->OrderItems->find();
      		$query->select(['name' => 'Items.name', 'total_price' => $query->func()->sum('Items.unit_price'), 'count' => $query->func()->count('Items.id')])
      		->contain(['Orders'])
      		->leftJoinWith('Items')
      		->group(['Items.id','Items.name'])
      		->order(['Items.sort_order'])
      		->where(['Orders.order_date' => $today]); // ->enableAutoFields(true);
      		
      		$gt = 0;
      		foreach ($query->all() as $item) {
      			$message .= "　・" . $item->name . " " . $item->count . "個 小計： " . number_format($item->total_price) . "円\n";
      			$gt += $item->total_price;
      		}
      		$message .= "\n　　合計： " . number_format($gt) . "円\n[hr]";
      		
      		// 集計（個別）
      		$sepQuery = $query->cleanCopy();
      		$sepQuery->select(['Orders.chatwork_account', 'name' => 'Items.name', 'total_price' => $query->func()->sum('Items.unit_price'), 'count' => $query->func()->count('Items.id')])
      		->group(['Items.id','Items.name','Orders.chatwork_account']);
      		
      		// chatwork_account ごとに分ける
      		$sepOrder = [];
      		foreach ($sepQuery->all() as $order) {
      			$sepOrder[$order->order->chatwork_account][] = $order;
      		}
      		
      		// メッセージを整理
      		foreach ($sepOrder as $account_id => $order) {
      			$message .= '[To:' . $account_id. ']';
      			$message .= "[piconname:" . $account_id. "] さんの注文\n\n";
      			$gt = 0;
      			foreach ($order as $item) {
      				$message .= "　・" . $item->name . " " . $item->count . "個 小計： " . number_format($item->total_price) . "円\n";
      				$gt += $item->total_price;
      			}
      			$message .= "\n　　合計： " . number_format($gt) . "円\n[hr]";
      		}

      		// メッセージポスト
      		$api->postRoomsMessages($roomId, $message);
      		return;
          
          break;
          
        case "注文":
          // 商品名をIDに変換　ない商品は無視されます。
      		$new_order_items= []; // 保存用にエンティティを生成
      		foreach ($reqmodel["data"] as $order_item) {
      			if (array_key_exists($order_item, $menuNames)) {
      				$entity = $this->Orders->OrderItems->newEntity();
      				$entity->item_id = $menuNames[$order_item];
      				$entity->number = 1;
      				$new_order_items[] = $entity;
      			}
      		}
      		if (0 == count($new_order_items)) {
      			$reqmodel["message"] .= "(sweat) 商品が見つかりません。 (sweat)";
      		} else {
      			// すでに注文がある場合は既存注文に追加
      			$addComment = "（新規）";
      			if (0 <> $query->count()) {
      				$row = $query->first();
      				// 既存注文を選ぶ
      				$new_order= $this->Orders->get($row->id);
      				$addComment = "（更新）";
      			} else {
      				// 新規注文を生成
      				$new_order = $this->Orders->newEntity();
      				$new_order->chatwork_account = $account_id;
      				$new_order->order_date= $today;
      			}
      			
      			// 商品を登録
      			$new_order->order_items = $new_order_items;
      			$this->Orders->save($new_order);
      			
      			$reqmodel["message"] .= " ありがとうございます、下記の通り受け付けました" . $addComment . " :) (" . $today . "分) \n\n";
      			// クエリ
      			$newQuery= $this->Orders->OrderItems->find();
      			$newQuery->select(['Orders.chatwork_account', 'name' => 'Items.name', 'total_price' => $query->func()->sum('Items.unit_price'), 'count' => $query->func()->count('Items.id')])
      			->contain(['Orders'])
      			->leftJoinWith('Items')
      			->group(['Items.id','Items.name'])
      			->order(['Items.sort_order'])
      			->where(['chatwork_account' => $account_id, 'order_date' => $today]);
      			$newOrder= $newQuery->all();
      			$total = 0;
      			foreach ($newOrder as $item) {
       				$reqmodel["message"] .= "　・" . $item->name . " " . $item->count . "個 小計： " . number_format($item->total_price) . "円\n";
       				$total += $item->total_price;
      			}
      			$reqmodel["message"] .= "\n　　合計： " . number_format($total) . "円\n";
      			// $reqmodel["message"] .= "(注文番号: " . $row->id . ")\n"; // 紛らわしいので一旦廃止
      		}
          
          break;
          
        case "メニュー":
          $reqmodel["message"] .= " メニューだよ、下記をコピーして注文する商品だけ残して送信してね(cracker)\n[code]注文\n";
      		foreach ($menu as $item) {
      			$reqmodel["message"] .= $item['name'] . "\n"; // $item['unit_price']
      		}
      		$reqmodel["message"] .= "[/code]";
      		
          break;
          
        case "キャンセル":
          // すでに注文がある場合は削除
      		if (0 <> $query->count()) {
      			$row = $query->first();
      			$entity = $this->Orders->get($row->id);
      			if ($this->Orders->delete($entity)) {
      				$reqmodel["message"] .= " キャンセルしたよ(y)\n";
      			} else {
      				$reqmodel["message"] .= " キャンセルできなかった、もう一回キャンセルしてね:*\n";
      			}
      		} else {
      			$reqmodel["message"] .= " 今日の注文はまだないよ(shake)\n";
      		}
          
          break;
          
        default:
          // not post Messages
      		return;
        
      }
    	
    	// 集計以外のメッセージポスト
    	$api->postRoomsMessages($roomId, $reqmodel["message"]);
      
    }
    
  }
  
  
  /*
   * CWからのリクエストをリクエストモデルに格納
   * リクエスト内容がなければ false を返す
   * リクエスト内容があれば $reqmodel に格納して true を返す
   */
  private function get_cw_request () {
    //リクエストモデル初期化
    flash_cw_request();
    
    //リクエスト加工
    // POST Body from Chatwork (Array not JSON)
  	$body = $this->request->getData();
   	$message_id = $body['webhook_event']['message_id'];
  	$account_id = $body['webhook_event']['account_id'];
  	$text = $body['webhook_event']['body'];
  	
    //リクエストがなければfalse
    if (empty($text)) return false;
    
    // コマンドとデータを取得
    $texts = explode($this->ORDER_SEP, $text);
    //1つめをリクエストモデルのcommandに格納
    $reqmodel["command"] = array_shift($texts);
    //以降をリクエストモデルのdataに格納
    $reqmodel["data"] = $texts;
    
    // TEXTを解析
  	// TODO: Controller HEAVY Refactoring! Refactoring! Refactoring!
  	// 「注文」で始まる： その後の各行を注文商品とする
  	// 「メニュー」で始まる：メニューを返す
  	// 「キャンセル」で始まる：$account_idの注文を取り消す
  	$reqmodel["message"] = '[rp aid=' . $account_id. ' to=' . $roomId. '-' . $message_id. ']'; // [rp aid={account_id} to={room_id}-{message_id}]
  	$reqmodel["message"] .= "[piconname:" . $account_id. "] さんへ\n";
  	// $reqmodel["message"] .= " 〜 おにぎりボットがお知らせします 8-)\n";
    
    return true;
    
  }
  
  
  /*
   * リクエストモデル初期化
   */
  private function flash_cw_request () {
    $reqmodel = [
      "message_id" => "",
      "account_id" => "",
      "command" => "",
      "data" => [],
      "message" => "",
    ];
  }
  
  
}

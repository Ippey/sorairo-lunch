<?php
namespace App\Controller;

use App\ChatWork\Webhook;
use App\ChatWork\Api;
use App\Service\ShopService;
use Cake\ORM\TableRegistry;
use Psr\Log\LogLevel;
use Composer\Config;
use Cake\Core\Configure;

/**
 * ChatworkWebhook Controller
 *
 * @property \App\Model\Table\ItemsTable $Items
 * @property \App\Model\Table\OrdersTable $Orders
 * @property Array $openDate 営業日カレンダー
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

		$this->openDate = Configure::read('openDate');
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

    	// 共通条件
    	$today = date('Y-m-d');
    	$roomId = env('CHATWORK_ROOM_ID');

    	// 営業日チェック
        $shopService = new ShopService();
        $isOrderAvailable = $shopService->isOrderAvailable();

    	// メニューを取得
    	$query = $this->Items->find('ActiveMenu');// ->find('list');
    	$query->enableHydration(false); // 結果をArrayで
    	$menu = $query->toArray(); // 全情報

    	// チェック向けに商品名のみの配列を準備
    	$menuNames = [];
    	foreach ($menu as $item) {
    		$menuNames[$item['name']] = $item['id'];
    	}

    	// POST Body from Chatwork (Array not JSON)
    	$body = $this->request->getData();
     	$message_id = $body['webhook_event']['message_id'];
    	$account_id = $body['webhook_event']['account_id'];
    	$text = $body['webhook_event']['body'];

    	// CHATWORK APIでリプ
    	// TOKEN発行者の発言になる
    	$api = new Api(env('CHATWORK_API_TOKEN'));

    	// 集計のみ別
    	if (preg_match('/^集計/', $text, $matches)) {
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
    	}

    	// TEXTを解析
    	// TODO: Controller HEAVY Refactoring! Refactoring! Refactoring!
    	// 「注文」で始まる： その後の各行を注文商品とする
    	// 「メニュー」で始まる：メニューを返す
    	// 「キャンセル」で始まる：$account_idの注文を取り消す
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
    		if (false == $isOrderAvailable) {
    		    $message .= "注文してくれてわるいんやけど、今日は休みやで、またきてなぁ〜(dance)";
    		} elseif (0 == count($new_order_items)) {
    			$message .= "(sweat) 商品が見つかりません。 (sweat)";
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

    			$message .= "注文受けたで。昼ごはんまでもう少し、頑張るんやで。" . $addComment . " :) (" . $today . "分) \n\n";
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
     				$message .= "　・" . $item->name . " " . $item->count . "個 小計： " . number_format($item->total_price) . "円\n";
     				$total += $item->total_price;
    			}
    			$message .= "\n　　合計： " . number_format($total) . "円\n";
    			// $message .= "(注文番号: " . $row->id . ")\n"; // 紛らわしいので一旦廃止
    		}

    	} elseif (preg_match('/^メニュー/', $text)) {
    	    if ($isOrderAvailable) {
    	        $message .= " メニューだよ、下記をコピーして注文する商品だけ残して送信してね(cracker)\n[code]注文\n";
    	    } else {
    	        $message .= "わるいんやけど、今日は休みやで、メニューだけ教えたるわ8-)\n[code]注文\n";
    	    }

    		foreach ($menu as $item) {
    			$message .= $item['name'] . "\n"; // $item['unit_price']
    		}
    		$message .= "[/code]";

    	} elseif (preg_match('/^キャンセル/', $text)) {
    		// すでに注文がある場合は削除
    		if (0 <> $query->count()) {
    			$row = $query->first();
    			$entity = $this->Orders->get($row->id);
    			if ($this->Orders->delete($entity)) {
    				$message .= " キャンセルしたよ(y)\n";
    			} else {
    				$message .= " キャンセルできなかった、もう一回キャンセルしてね:*\n";
    			}
    		} else {
    			$message .= " 今日の注文はまだないよ(shake)\n";
    		}

    	} else {
    		// not post Messages
    		return;
    	}

    	// 集計以外のメッセージポスト
    	$api->postRoomsMessages($roomId, $message);
    }
}

<?php
use Migrations\AbstractSeed;

/**
 * Items seed.
 */
class ItemsSeed extends AbstractSeed
{
    /**
     * Run Method.
     *
     * Write your database seeder using this method.
     *
     * More information on writing seeds is available here:
     * http://docs.phinx.org/en/latest/seeding.html
     *
     * @return void
     */
    public function run()
    {
        $data = [
            [
                'name' => '梅',
                'description' => 'どこの梅を使っているのか聞きたい',
                'unit_price' => 150,
                'sort_order' => 1,
                'is_disable' => 0,
                'created' => date('Y-m-d H:i:s'),
                'modified' => date('Y-m-d H:i:s'),
            ],
            [
                'name' => '塩',
                'description' => 'シンプル is ベスト',
                'unit_price' => 150,
                'sort_order' => 2,
                'is_disable' => 0,
                'created' => date('Y-m-d H:i:s'),
                'modified' => date('Y-m-d H:i:s'),
            ],
            [
                'name' => 'いわしぶし',
                'description' => 'いわしの「ぶし」だよ',
                'unit_price' => 150,
                'sort_order' => 3,
                'is_disable' => 0,
                'created' => date('Y-m-d H:i:s'),
                'modified' => date('Y-m-d H:i:s'),
            ],
            [
                'name' => '昆布',
                'description' => 'どこの昆布を使っているのか聞きたい',
                'unit_price' => 150,
                'sort_order' => 4,
                'is_disable' => 0,
                'created' => date('Y-m-d H:i:s'),
                'modified' => date('Y-m-d H:i:s'),
            ],
            [
                'name' => 'さけ',
                'description' => '定番のさけ',
                'unit_price' => 150,
                'sort_order' => 5,
                'is_disable' => 0,
                'created' => date('Y-m-d H:i:s'),
                'modified' => date('Y-m-d H:i:s'),
            ],
            [
                'name' => 'スタミナ',
                'description' => 'お昼からも元気に！',
                'unit_price' => 150,
                'sort_order' => 6,
                'is_disable' => 0,
                'created' => date('Y-m-d H:i:s'),
                'modified' => date('Y-m-d H:i:s'),
            ],
            [
                'name' => 'しょうゆチーズ',
                'description' => '和洋の奇跡',
                'unit_price' => 150,
                'sort_order' => 7,
                'is_disable' => 0,
                'created' => date('Y-m-d H:i:s'),
                'modified' => date('Y-m-d H:i:s'),
            ],
            [
                'name' => 'さばゆず胡椒',
                'description' => '爽やかにピリリ',
                'unit_price' => 150,
                'sort_order' => 8,
                'is_disable' => 0,
                'created' => date('Y-m-d H:i:s'),
                'modified' => date('Y-m-d H:i:s'),
            ],
            [
                'name' => 'じゃこラー',
                'description' => 'じゃこにラー油のパンチ',
                'unit_price' => 150,
                'sort_order' => 9,
                'is_disable' => 0,
                'created' => date('Y-m-d H:i:s'),
                'modified' => date('Y-m-d H:i:s'),
            ],
            [
                'name' => 'ガパオ',
                'description' => '鶏そぼろとバジル',
                'unit_price' => 150,
                'sort_order' => 10,
                'is_disable' => 0,
                'created' => date('Y-m-d H:i:s'),
                'modified' => date('Y-m-d H:i:s'),
            ],
            [
                'name' => 'おみそ汁',
                'description' => 'おにぎりのベストフレンド',
                'unit_price' => 50,
                'sort_order' => 11,
                'is_disable' => 0,
                'created' => date('Y-m-d H:i:s'),
                'modified' => date('Y-m-d H:i:s'),
            ],

        ];

        $table = $this->table('items');
        $table->insert($data)->save();
    }
}

# おにぎり<ソライロ・ピクニックカフェ>オーダーシステム
ひらばでソライロ・ピクニックカフェのおにぎりを注文するシステム、注文はチャットワークから。


## 初期配置

### DB
```
$ mysql -u root -p
MariaDB [sorairo_lunch]> CREATE DATABASE sorairo_lunch;
$ mysql -u root -p sorairo_lunch
```

### ソースを取得 - git clone
```
$ git clone git@github.com:hira8-tech/sorairo-lunch.git path/to/webapp
$ cd path/to/webapp
$ composer install
```

### テーブル作成 - migration
```
$ bin/cake migrations migrate
```
- migrationファイル作成 は `config/Migrations/create_migration.txt` を参考に

### 初期データ挿入 - seed
```
$ bin/cake migrations seed
```
-  （例：データなしのseedファイル生成） `bin/cake bake seed Items`


### アクセス先の設定：任意
```
$ ln -s webapp/webroot path/to/htdocs
```

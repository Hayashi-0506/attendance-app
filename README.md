# attendance-app

## プロジェクト名
COACHTECH 勤怠管理アプリ

## 概要
COACHTECHの模擬案件テストとして作成した成果物です。
勤怠管理から出勤、退勤、休憩入、休憩出が行えます。
ユーザーは勤怠の修正を行い、承認依頼を行えます。
また、過去6カ月の勤怠状況の集計を行えます。
管理者は全ユーザーの勤怠の確認と、各ユーザーの勤怠申請の承認を行えます。

## ER図
![テーブル定義のER図](./images/テーブル仕様書_ER図.png)

## 環境構築手順

1. **プロジェクトの配置場所へ移動**
- フォルダ作成
    ```bash
    mkdir laravel-practice
    cd laravel-practice
    ```

2．**クローンを取得**
- クローン
    ```bash
    git clone https://github.com/Hayashi-0506/attendance-app.git attendance-app
    cd attendance-app
    ```

3. **パッケージをインストールする**
    ```bash
    docker run --rm \
        -u "$(id -u):$(id -g)" \
        -v "$(pwd):/var/www/html" \
        -w /var/www/html \
        -e COMPOSER_CACHE_DIR=/tmp/composer_cache \
        laravelsail/php82-composer:latest \
        composer install
    ```

4. **環境設定ファイルを作成する**
    ```bash
    cp .env.example .env
    ```

5. **Laravel Sailのインストール**
- Laravel Sailをインストール
    ```bash
    docker run --rm \
        -u "$(id -u):$(id -g)" \
        -v "$(pwd):/var/www/html" \
        -w /var/www/html \
        -e COMPOSER_CACHE_DIR=/tmp/composer_cache \
        laravelsail/php82-composer:latest \
        composer require laravel/sail --dev
    ```

# Sailの設定ファイルをパブリッシュ（MySQLを選択）
    ```bash
    docker run --rm \
        -u "$(id -u):$(id -g)" \
        -v "$(pwd):/var/www/html" \
        -w /var/www/html \
        -e COMPOSER_CACHE_DIR=/tmp/composer_cache \
        laravelsail/php82-composer:latest \
        php artisan sail:install --with=mysql
    ```

# ※M1/M2/M3 Mac（Apple Silicon）をお使いの方

Apple Silicon搭載のMacでは、`sail up -d`実行時に以下のエラーが発生することがあります：
    ```bash
    no matching manifest for linux/arm64/v8
    ```

解決方法: `compose.yaml`を開き、mysqlサービスに`platform: 'linux/amd64'`を追加してください。
    ```bash
    mysql:
        image: 'mysql/mysql-server:8.0'
        platform: 'linux/amd64'  # ← この行を追加
        ports:
    ```

6. **.env ファイルの設定**
- .env ファイルを開き、データベース接続情報が以下と一致していることを確認します。
    ```bash
    DB_CONNECTION=mysql
    DB_HOST=mysql
    DB_PORT=3306
    DB_DATABASE=laravel
    DB_USERNAME=sail
    DB_PASSWORD=password

    MAIL_MAILER=smtp
    MAIL_HOST=mailpit
    MAIL_PORT=1025
    ```

7. **Sailの起動とエイリアス設定**
- Sailをバックグラウンドで起動
    ```bash
    ./vendor/bin/sail up -d
    ```

- エイリアスを設定して 'sail' だけでコマンドを実行できるようにする
    ```bash
    echo "alias sail='[ -f sail ] && bash sail || bash vendor/bin/sail'" >> ~/.zshrc
    ```

- または bash の場合
    ```bash
    echo "alias sail='[ -f sail ] && bash sail || bash vendor/bin/sail'" >> ~/.bashrc
    ```

- シェルを再起動するか、新しいターミナルを開いてエイリアスを有効にする
    ```bash
    exec $SHELL
    ```

8. **フロントエンドのセットアップ (Vite & Tailwind CSS)**
本プロジェクトでは、フロントエンドのスタイリングにTailwind CSSを使用します。

- 1. NPM依存パッケージのインストール
> 重要: sail npm install を実行する前に、必ずSailコンテナが起動していることを確認してください。
    ```bash
    sail npm install
    ```

- 2. Tailwind CSSのインストール
    ```bash
    sail npm install -D tailwindcss@^3.4.0 postcss autoprefixer
    sail npm install alpinejs
    ```

- 3. Vite開発サーバーの起動
    ```bash
    sail npm run dev
    ```
注意: sail npm run dev は実行したままにしておく必要があります。

9. **アプリケーションキーの生成**
-ルートで以下のコマンドを実行する
    ```bash
    sail artisan key:generate
    ```

10. データベースのマイグレーションと初期データ投入
- 以下のコマンドでテーブルを作成し、初期データを投入します。
    ```bash
    sail artisan migrate --seed
    ```

- ※既存のデータベースをリセットしたい場合は以下を実行してください。
        ```bash
    sail artisan migrate:fresh --seed
    ```

## 使用技術
- PHP 8.5.7
- Laravel 10.x + Sail
- DB : MySQL 8.0
- Webサーバー : Nginx
- フロントエンド : Vite, Tailwind CSS ^3.4.0
- 開発ツール : Docker, Laravel Sail, phpMyAdmin

## APIエンドポイント一覧
- http://localhost/api/v1/attendance-records

## 開発環境URL
- ログイン画面：http://localhost/login
- 管理者用ログイン画面：http://localhost/admin/login

## 開発者
- 林 佑一

# BtoB 施設管理 SaaS 開発ガイド（Laravel 12 + Fortify + Inertia + Vue3 + PrimeVue）

このドキュメントは、

**「このリポジトリを渡されたエンジニアが、環境構築〜今後の実装方針まで一気に理解できること」**

を目的に作成されています。

---

## 0. 全体方針まとめ

-   目的：
    BtoB 向けの **施設管理 SaaS** を「モダンモノリス＋クリーンアーキテクチャ（軽量）」で構築する
-   認証関連（Laravel 12 前提）
    -   UI 付きスターターキット（Breeze / Jetstream）は Laravel 12 では「新 Starter Kits に置き換わり、今後追加 update はされない」扱い
    -   本プロジェクトでは **Laravel Fortify** を採用し、
        フロントは **Inertia + Vue3 + PrimeVue** で自前実装
    -   将来的に **Laravel Sanctum** を導入し、SPA / モバイル / 外部サービス向け API 認証にも対応する計画
-   UI／フロントエンド
    -   Inertia.js を用いた **モダンモノリス構成**（Laravel ルート → Vue ページ）
    -   フロントスタック：Vue3 + TypeScript + PrimeVue（無料テーマのみ）
    -   Tailwind は後から徐々に適用（最初は PrimeVue 既成スタイル優先）
-   アーキテクチャ
    -   **軽量クリーンアーキテクチャ＋モジュラーモノリス**
        -   レイヤ：Domain / Application / Infrastructure / Presentation(Http+Inertia)
        -   モジュール：System / Auth / Facility / Group / User などのドメイン単位で整理
    -   過度な抽象化は避け、実務で扱いやすいバランスを優先
-   インフラ（開発環境）
    -   Docker（docker compose）
    -   PHP 8.3（php-fpm）
    -   Nginx 1.28
    -   Postgres 16
    -   Valkey 8.1（Redis 互換キャッシュ）
    -   `just` によるコマンドランナー

---

## 1. ドメイン概要（方式 A）

### 1.1 概念

-   **Facility（施設）**
    -   SaaS の基本単位
    -   1 クライアント = 1 施設を基本としつつ、複数施設もサポート
-   **Facility Group（グループ）**
    -   複数施設を束ねる上位概念（法人・系列など）
-   **User（ユーザー）**
    -   ログインするユーザー（職員・管理者など）
    -   ログインしないユーザー（同乗者・利用者などの人物情報）

### 1.2 関係

-   `facility_group` 1 : N `facility`
-   `user` N : N `facility`（中間テーブル `facility_user`）
-   `users.current_facility_id` で「今操作している施設」を表現
-   各業務テーブルは必ず `facility_id` を持つ（方式 A のスコープルール）

### 1.3 アクセススコープ

-   **施設スコープ**
    -   通常画面は `current_facility_id` に紐づくデータのみ参照／操作
-   **グループスコープ**
    -   同一 `facility_group` 内で、権限次第で横断集計
-   将来的にサブドメイン（`https://shizuoka.example.com`）を導入
    -   方式 A：サブドメイン → `facility_id` をミドルウェアで解決

---

## 2. 技術スタック

### 2.1 バックエンド

-   Laravel 12
-   PHP 8.3 (FPM)
-   Laravel Fortify（認証バックエンド）
-   Laravel Sanctum（将来導入予定：SPA / API 認証）
-   Postgres 16（単一 DB マルチテナント：facility_id スコープ）
-   Valkey 8.1（Redis 互換キャッシュ：セッション＆アプリケーションキャッシュ）

### 2.2 フロントエンド

-   Inertia.js（Laravel + Vue のモダンモノリス）
-   Vue 3
-   TypeScript
-   PrimeVue（無料テーマのみ）
-   Tailwind CSS（後から必要に応じて）

### 2.3 インフラ（開発）

-   Docker / docker compose
-   Nginx 1.28
-   `just`（開発コマンドランナー）

---

## 3. アーキテクチャ概要

### 3.1 レイヤ構造（軽量クリーンアーキ）

```
app/
  Domain/
    Facility/
    Group/
    User/
    System/
  Application/
    Facility/UseCases/
    Group/UseCases/
    User/UseCases/
    System/UseCases/
  Infrastructure/
    Persistence/
    Cache/
    Auth/
  Http/
    Controllers/
    Middleware/
    Requests/

```

-   **Domain**
    -   エンティティ／値オブジェクト／ドメインサービス
    -   他レイヤに依存しない
-   **Application**
    -   UseCase（ユースケース）単位でビジネスフローを記述
    -   Domain に依存するが、Infrastructure には依存しない
-   **Infrastructure**
    -   Repository 実装（Eloquent 等）、外部 API、キャッシュなど
    -   他レイヤに依存して OK
-   **Presentation（Http + Inertia + Vue）**
    -   Controller：Application の UseCase を呼び出し、Inertia レスポンスを返す
    -   Vue：画面表示とユーザー操作

### 3.2 モジュラーモノリスとしての分割

-   **例：**
    -   System（ヘルスチェック・ステータス）
    -   Auth（Fortify 連携、ログイン関連）
    -   Facility（施設管理）
    -   Group（グループ管理）
    -   User（ユーザー管理）

**方針：**

-   まずは **System / Auth / Facility** の 3 モジュールから始める
-   ServiceProvider（例：`AppServiceProvider` or 独立 Provider）で
    Repository インターフェースへのバインドをモジュール毎に追加

---

## 4. リポジトリ構成（推奨）

プロジェクトルートを `facility-saas/` とした例：

```
facility-saas/
  docker/
    app/
      Dockerfile
    nginx/
      default.conf
    env/
      app.env
      db.env
  src/
    app/
    bootstrap/
    config/
    database/
    public/
    resources/
    routes/
    ...
  justfile
  docker-compose.yml
  README.md（←このファイル）

```

---

## 5. 開発環境（Docker）構成

### 5.1 docker-compose.yml（例）

```yaml
version: "3.9"

services:
    app:
        build:
            context: ./docker/app
        container_name: facility_app
        working_dir: /var/www/html
        volumes:
            - ./src:/var/www/html
            - app-vendor:/var/www/html/vendor
            - app-node-modules:/var/www/html/node_modules
        depends_on:
            db:
                condition: service_healthy
            cache:
                condition: service_started
        restart: unless-stopped
        env_file:
            - ./docker/env/app.env

    web:
        image: nginx:1.28
        container_name: facility_web
        ports:
            - "8080:80"
        volumes:
            - ./src:/var/www/html
            - ./docker/nginx/default.conf:/etc/nginx/conf.d/default.conf
        depends_on:
            - app
        restart: unless-stopped

    db:
        image: postgres:16
        container_name: facility_db
        env_file:
            - ./docker/env/db.env
        ports:
            - "54321:5432"
        volumes:
            - db-data:/var/lib/postgresql/data
        restart: unless-stopped
        healthcheck:
            test: ["CMD-SHELL", "pg_isready -U facility -d facility"]
            interval: 10s
            timeout: 5s
            retries: 5

    cache:
        image: valkey/valkey:8.1
        container_name: facility_cache
        ports:
            - "6379:6379"
        command: ["valkey-server", "--save", "", "--appendonly", "no"]
        restart: unless-stopped

volumes:
    db-data:
    app-vendor:
    app-node-modules:
```

### 5.2 app コンテナ用 Dockerfile

`docker/app/Dockerfile`：

```docker
FROM php:8.3-fpm

RUN apt-get update && apt-get install -y --no-install-recommends \
    git \
    unzip \
    libpq-dev \
    libicu-dev \
    libzip-dev \
    zlib1g-dev \
    curl \
    && docker-php-ext-install pdo pdo_pgsql intl zip opcache \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

RUN curl -fsSL https://deb.nodesource.com/setup_20.x | bash - \
    && apt-get update \
    && apt-get install -y --no-install-recommends nodejs \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

RUN usermod -u 1000 www-data && groupmod -g 1000 www-data
USER www-data

```

### 5.3 Nginx 設定

`docker/nginx/default.conf`：

```
server {
    listen 80;
    server_name localhost;

    root /var/www/html/public;
    index index.php index.html;

    location ~ /\. {
        deny all;
    }

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        try_files $uri =404;

        include fastcgi_params;
        fastcgi_pass app:9000;

        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        fastcgi_param DOCUMENT_ROOT $realpath_root;
        fastcgi_param PATH_INFO $fastcgi_path_info;
    }

    location ~* \.(js|css|png|jpg|jpeg|gif|svg|ico|woff2?|ttf|otf|eot)$ {
        try_files $uri =404;
        expires 7d;
        access_log off;
    }
}

```

### 5.4 env ファイル

`docker/env/db.env`：

```
POSTGRES_DB=facility
POSTGRES_USER=facility
POSTGRES_PASSWORD=secret

```

`docker/env/app.env`：

```
APP_ENV=local
APP_DEBUG=true
APP_URL=http://localhost:8080

DB_CONNECTION=pgsql
DB_HOST=db
DB_PORT=5432
DB_DATABASE=facility
DB_USERNAME=facility
DB_PASSWORD=secret

CACHE_STORE=redis
CACHE_DRIVER=redis

REDIS_HOST=cache
REDIS_PORT=6379
REDIS_PASSWORD=null

SESSION_DRIVER=redis
SESSION_LIFETIME=120

```

---

## 6. just を使った開発コマンド

### 6.1 justfile（例）

プロジェクトルート `facility-saas/justfile`：

```
set shell := ["bash", "-cu"]

# =========
# 基本操作
# =========

up:
    docker compose up -d

down:
    docker compose down

restart:
    just down
    just up

build:
    docker compose build

ps:
    docker compose ps

# =========
# Laravel / PHP
# =========

bash:
    docker compose exec app bash

art cmd:
    docker compose exec app php artisan {{cmd}}

migrate:
    docker compose exec app php artisan migrate

seed:
    docker compose exec app php artisan db:seed

test:
    docker compose exec app php artisan test

tinker:
    docker compose exec app php artisan tinker

# =========
# Node / Vite
# =========

npm script:
    docker compose exec app npm run {{script}}

npm-install:
    docker compose exec app npm install

# =========
# Logs
# =========

logs-app:
    docker compose logs -f app

logs-web:
    docker compose logs -f web

logs-db:
    docker compose logs -f db

logs-cache:
    docker compose logs -f cache

# =========
# Clean up
# =========

prune:
    docker system prune -f

```

### 6.2 よく使うコマンド

-   コンテナ起動：`just up`
-   コンテナ停止：`just down`
-   app コンテナに入る：`just bash`
-   マイグレーション：`just migrate`
-   任意の artisan：`just art "route:list"`
-   Vite dev：`just npm dev`

---

## 7. 環境構築手順（ゼロから）

1. **プロジェクトディレクトリ作成**

    ```bash
    mkdir facility-saas
    cd facility-saas
    mkdir -p docker/app docker/nginx docker/env src

    ```

2. **上記の docker-compose.yml / Dockerfile / default.conf / env / justfile を配置**
3. **Docker ビルド & 起動**

    ```bash
    just build
    just up
    just ps

    ```

4. **Laravel プロジェクト作成**

    ```bash
    just bash
    cd /var/www/html
    composer create-project laravel/laravel .
    cp .env.example .env
    php artisan key:generate

    ```

5. **.env と app.env の整合（DB / Redis 設定）を確認**
6. **マイグレーション & 動作確認**

    ```bash
    php artisan migrate
    exit

    ```

7. ブラウザで `http://localhost:8080` にアクセス → Laravel 初期画面が表示されれば OK

---

## 8. 認証方針：Fortify + 将来の Sanctum

### 8.1 Fortify（Web 認証の中核）

Laravel 12 の Starter Kits も内部で Fortify を利用して認証を提供している。

本プロジェクトでは UI を自作するため、Fortify を直接導入し、Inertia + Vue から利用する。

### 導入ステップ（概要）

1. Fortify インストール

    ```bash
    just art "vendor:publish --provider=\"Laravel\Fortify\FortifyServiceProvider\""
    composer require laravel/fortify

    ```

2. 設定ファイル `config/fortify.php` を編集し、利用する機能を有効化
    - ログイン
    - 新規登録
    - パスワードリセット
    - メールアドレス確認
    - 必要に応じて 2FA など
3. `App\Providers\FortifyServiceProvider` で

    `Fortify::loginView`, `Fortify::registerView` などのコールバックを Inertia 対応に変更

    → Blade ビューレンダリングではなく、JSON レスポンス or Inertia レスポンスで返却

4. Vue 側では

    Fortify が提供するエンドポイント（例：`/login`, `/register`）に対し

    `axios` or `Inertia.post()` でリクエスト

### 8.2 Sanctum（将来の API 認証）

-   目的：SPA / モバイル / 外部連携 に対する **トークンベース or セッションクッキー認証**を提供
-   Fortify は「認証機能のバックエンド」、Sanctum は「API 向けの認証レイヤ」として補完関係

将来導入時の想定：

-   Web：Fortify + セッション（Inertia 画面）
-   SPA / 外部サービス：Sanctum + API トークン or セッションクッキー

---

## 9. フロントエンド方針（Inertia + Vue + PrimeVue）

### 9.1 Inertia.js

-   Laravel 側で `Inertia::render('Page/Name', [...props])`
-   Vue 側で `resources/js/Pages/Page/Name.vue` を作成
-   ルーティングは Laravel の `routes/web.php` で定義

### 9.2 Vue + TypeScript

-   エントリポイント：`resources/js/app.ts`
-   `tsconfig.json` で `@` → `resources/js` のパスエイリアス
-   徐々に strict に寄せる方針（最初は strict=false）

### 9.3 PrimeVue

-   `resources/js/app.ts` で PrimeVue をインポートし、アプリに登録
-   テーマは **無料テーマ**（例：Aura 系）から選択
-   PrimeIcons を導入してアイコンを利用
-   Breeze のようなプリセットは使わず、
    **レイアウト（ヘッダー／サイドバー）を Inertia + Vue + PrimeVue で自作**

---

## 10. ドメイン実装の最初の一歩（フェーズ 1: Auth）

### 10.1 フェーズ 1 のゴール

-   Fortify によるログイン／ログアウト／ユーザー登録／パスワードリセット
-   Inertia + Vue + PrimeVue ベースのログイン画面
-   System モジュールに簡易 `/system-status` API（キャッシュ付き）を残しつつ整理

### 10.2 Recommended Steps

1. Fortify 導入＆設定
2. `/login` `/register` の Inertia ページを PrimeVue で実装
3. 認証後のダッシュボード（仮）を Inertia ページで実装
4. `System` モジュールの `GetSystemStatus` UseCase を整理（既存実装のクリーンアップ）
5. `/system-status` を「ログイン後のみアクセス可」に変更し、

    認証が効いていることを確認

---

## 11. 今後のロードマップ（フェーズ案）

1. **フェーズ 1：Auth**
    - Fortify + Inertia + Vue + PrimeVue でログイン周りを完成させる
2. **フェーズ 2：施設ドメインの基礎**
    - マイグレーション：`facility_groups`, `facilities`, `facility_user`, `users.current_facility_id`
    - Domain / Application / Infrastructure 層で `ListFacilities` UseCase を実装
    - Inertia + PrimeVue で「施設一覧画面」を作成
3. **フェーズ 3：current_facility スコープと切り替え UI**
    - `SetCurrentFacility` ミドルウェア
    - Inertia の Shared Props で `currentFacility` をフロントへ配布
    - ヘッダーに「施設切り替えドロップダウン」を実装
4. **フェーズ 4：グループ管理／横断集計**
    - グループ管理者ロール（spatie/permission or Laravel 標準 Gate/Policy）
    - グループ単位の施設横断ダッシュボード（PrimeVue DataTable など）
5. **フェーズ 5：Sanctum による API 公開**
    - SPA 用 API (Inertia からも呼べる)
    - 外部システム向け Token 認証
6. **フェーズ 6：品質向上**
    - テスト（Pest）、静的解析（Larastan）、コードスタイル（Pint）
    - CI（GitHub Actions など）

---

## 12. 新規参加エンジニア向け「最初の 30 分ガイド」

1. **前提ツールをインストール**
    - Docker / Docker Desktop
    - `just`（`brew install just`）
    - Git / Node.js（ローカルで直接使う機会は少ないがあった方がよい）
2. **リポジトリを取得**

    ```bash
    git clone <repo-url> facility-saas
    cd facility-saas

    ```

3. **コンテナ起動**

    ```bash
    just build
    just up
    just ps

    ```

4. **Laravel 起動確認**
    - ブラウザで `http://localhost:8080` にアクセス
        → Laravel 画面が出れば OK
5. **app コンテナに入る**

    ```bash
    just bash
    php artisan about
    php artisan route:list

    ```

6. **認証動作確認（フェーズ 1 以降）**
    - `/register` でユーザー作成（Inertia + PrimeVue の画面想定）
    - `/login` でログイン → `/dashboard` or `/system-status` に遷移することを確認

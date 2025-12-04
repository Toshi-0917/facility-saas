# CLAUDE.md

このファイルは、Claude Code（claude.ai/code）がこのリポジトリで作業する際のガイダンスを提供します。

## アーキテクチャ概要

このプロジェクトは **Laravel 12** と **Inertia.js + Vue 3** を使用した施設管理 SaaS アプリケーションで、**クリーンアーキテクチャ** の原則に従っています。

### コアアーキテクチャレイヤー

```
App/
├── Domain/                          # ビジネスルールとエンティティ
├── Application/System/UseCases      # アプリケーション固有のビジネスロジック
├── Http/Controllers                 # HTTP リクエストハンドラー
│   ├── Auth/                        # 認証コントローラー
│   ├── ProfileController            # ユーザープロフィール管理
│   └── SystemStatusController       # システムモニタリング
├── Models/                          # Eloquent モデル
├── Infrastructure/                  # 外部サービスと統合
└── Providers/                       # サービスプロバイダー
```

**アーキテクチャパターン**：プロジェクトは **Use Case パターン** を使用しており、ビジネスロジックは Use Cases（例：`GetSystemStatus`）に分離されています。コントローラーはこれらの Use Cases を依存性注入で呼び出し、テスト性と関心の分離を促進します。

### フロントエンドスタック

- **フレームワーク**：Vue 3 + Inertia.js（サーバーサイドルーティング、SPA 体験）
- **スタイリング**：Tailwind CSS（Forms プラグイン付き）
- **ビルドツール**：Vite + Laravel プラグイン
- **アセット**：`resources/js/` と `resources/css/` に配置

### バックエンドスタック

- **フレームワーク**：Laravel 12
- **データベース**：PostgreSQL 16（Docker で構成）
- **キャッシュ**：Valkey 8.1（Redis 互換）
- **認証**：Laravel Sanctum（API 認証）
- **ORM**：Eloquent
- **テスト**：PHPUnit（Feature と Unit テストスイート）

## 開発環境

### Docker セットアップ

Docker Compose でサービスを管理：

- **app**：Laravel アプリケーション（PHP 8.2+ + Node.js）
- **web**：Nginx ウェブサーバー（ポート 8080）
- **db**：PostgreSQL 16（ポート 54321）
- **cache**：Valkey キャッシュサーバー（ポート 6379）

ボリューム構成：`app-vendor` と `app-node-modules` で依存関係を永続化

### よく使うコマンド

#### 開発サーバー

```bash
# すべての Docker サービスを起動
just up

# サービスを停止
just down

# サービスを再起動
just restart

# ログの確認
just logs-app      # Laravel アプリケーションログ
just logs-web      # Nginx ログ
just logs-db       # データベースログ
just logs-cache    # キャッシュログ
```

#### Laravel Artisan

```bash
just art migrate              # データベースマイグレーション実行
just art seed                 # データベースシーダー実行
just art test                 # テスト実行（キャッシュクリア含む）
just art tinker               # 対話型シェル（REPL）
```

#### フロントエンド開発

```bash
npm run dev      # Vite 開発サーバー起動（Docker 内で実行）
npm run build    # 本番用ビルド
npm install      # 依存関係をインストール
```

#### データベース管理

```bash
just migrate     # マイグレーション実行
just seed        # シーダー実行
```

#### Docker コンテナアクセス

```bash
just bash        # app コンテナで bash シェルを開く
just ps          # 実行中のコンテナを表示
```

#### ビルド＆クリーンアップ

```bash
just build       # Docker イメージを再ビルド
just prune       # Docker システムをクリーンアップ（不要なイメージ/コンテナ）
```

### テスト実行

```bash
# すべてのテストを実行
just art test

# 特定のテストを実行
docker compose exec app php artisan test --filter=TestName

# 単一テストファイルを実行
docker compose exec app php artisan test tests/Feature/ProfileTest.php

# Unit テストのみを実行
docker compose exec app ./vendor/bin/phpunit tests/Unit

# Feature テストのみを実行
docker compose exec app ./vendor/bin/phpunit tests/Feature
```

**テスト構成**：`phpunit.xml` は SQLite インメモリデータベースを使用してテストの分離を実現します。テストスイートは `Unit` と `Feature` ディレクトリに分かれています。

## 主要パターンと慣例

### Use Case パターン

ビジネスロジックは Use Cases にカプセル化されます：

```php
// 例：App/Application/System/UseCases/GetSystemStatus.php
class GetSystemStatus {
    public function __construct(private Cache $cache) {}

    public function __invoke(): array {
        return $this->cache->remember('system_status', now()->addSeconds(5), ...);
    }
}
```

コントローラーは依存性注入で Use Cases を呼び出します：

```php
// コントローラー内
public function __invoke(GetSystemStatus $useCase) {
    $status = $useCase();
    return response()->json($status);
}
```

### ルーティング

- **Web ルート**：`src/routes/web.php`（Inertia ページ、認証ミドルウェア付き）
- **認証ルート**：`src/routes/auth.php`（認証コントローラーのルート）
- **API ルート**：未実装、必要に応じて `routes/api.php` を拡張
- **コンソールルート**：`src/routes/console.php`（Artisan コマンド）

### モデルとデータベース

- Eloquent モデル：`app/Models/`
- マイグレーション：`database/migrations/`
- シーダー：`database/seeders/`
- データベース接続：PostgreSQL（`config/database.php` で構成）

### リクエスト検証

Form Requests がバリデーション処理を担当：

```
app/Http/Requests/
├── Auth/LoginRequest.php
├── ProfileUpdateRequest.php
└── ... (他の検証)
```

### フロントエンドコンポーネント

再利用可能な Vue 3 コンポーネント（Tailwind CSS を使用）：

```
resources/js/
├── Components/     # 再利用可能な UI コンポーネント
├── Layouts/        # ページレイアウト（AuthenticatedLayout、GuestLayout）
├── Pages/          # フルページコンポーネント（認証、ダッシュボード、プロフィール）
└── bootstrap.js    # Vite プラグイン設定と Inertia 初期化
```

ページは Inertia のサーバーサイドルーティングで遅延読み込みされます。

## キャッシュ構成

アプリケーションは **Valkey**（Redis 互換）をキャッシュドライバーとして使用します。

**キャッシュドキュメント**：
- `docs/CACHE_SETUP.md`：設定手順
- `docs/CACHE_LEARNING_GUIDE.md`：包括的な学習ガイド

主要なキャッシュ実装：
- `GetSystemStatus` Use Case でのシステムステータスキャッシュ（5秒 TTL）
- Valkey 経由のセッションストレージ
- Cache ファサード経由のアプリケーションレベルキャッシング

## パフォーマンスとセキュリティ

### ミドルウェア

- **認証**：保護されたルートの `auth` ミドルウェア
- **メール検証**：`verified` ミドルウェア（メール検証が必須）
- **Inertia 処理**：`HandleInertiaRequests` ミドルウェア（認証情報をフロントエンドと共有）

### データベースと ORM

- Eloquent はパラメータ化クエリで SQL インジェクションを防止
- モデル（`app/Models/User.php`）は一括割り当て保護を使用
- マイグレーションはスキーマバージョニングを提供

### HTTPS とセキュリティ

- 本番環境：Nginx で HTTPS を設定
- CSRF 保護：Laravel 組み込み（API は Sanctum）
- パスワードハッシング：Laravel の Hash ファサード（bcrypt）

## Git ワークフロー

- **コミットフォーマット**：Conventional Commits（例：`feat:`、`fix:`、`chore:`、`docs:`）
- **ブランチ戦略**：フィーチャーブランチを main にマージ
- **最近の変更**：キャッシュ構成セットアップ、システムステータスエンドポイント、Docker 環境セットアップ

## 環境構成

主要な環境変数（`src/.env` 内）：

```
APP_NAME=Facility
APP_ENV=local
APP_DEBUG=true
DB_CONNECTION=pgsql
DB_HOST=db
DB_PORT=5432
DB_DATABASE=facility
DB_USERNAME=facility
CACHE_STORE=valkey
```

すべての使用可能なオプションについては `src/.env.example` を参照してください。

## Claude Code との統合

このリポジトリには以下が含まれています：

- **justfile**：よく使う開発タスク用の `just` コマンド
- **CLAUDE.md**（このファイル）：アーキテクチャとワークフローのガイダンス
- **.claude/settings.local.json**：Claude Code のローカル設定

`just` コマンドを使用して、長い Docker Compose コマンドをショートカットで実行できます。

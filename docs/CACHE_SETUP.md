# キャッシュ設定のセットアップガイド

## 背景

Laravel アプリケーションのキャッシュ機能を Redis (Valkey) で正常に動作させるための設定手順です。

## 実装内容

### 1. キャッシュ暗号化設定 (`src/config/cache.php`)

```php
'encrypt' => false,  // 開発環境ではキーを見やすくする
```

**理由：**
- 開発環境でキャッシュキーをデバッグしやすくするため
- 本番環境では必要に応じて `true` に設定

### 2. Docker 環境設定 (`docker/env/app.env`)

```
APP_NAME=facility-saas
APP_KEY=base64:RZJAdo5s9bgHXFckrRwmx7Wt6VJxIDDw90qzQhvNDMc=
```

**理由：**
- `APP_NAME`: キャッシュプレフィックスの生成に使用
- `APP_KEY`: 暗号化機能に必要な秘密鍵
- Docker 起動時に `env_file` から環境変数として読み込まれる

### 3. コンテナ内の設定更新

Docker コンテナ再構築後に、以下のコマンドを実行：

```bash
# APP_NAME を更新
docker exec facility_app sed -i 's/APP_NAME=Laravel/APP_NAME=facility-saas/' .env

# 設定キャッシュをクリア・再生成
docker exec facility_app sh -c 'rm -f bootstrap/cache/*.php'
docker exec facility_app php artisan config:cache
```

## 検証方法

### キャッシュが正常に保存されているか確認

```bash
# Valkey に接続して確認
docker exec facility_cache valkey-cli KEYS '*'
# → "facility-saas-cache-demo_random_string" などが表示される

# キャッシュの値を確認
docker exec facility_cache valkey-cli GET "facility-saas-cache-system_status"

# TTL (有効期限) を確認
docker exec facility_cache valkey-cli TTL "facility-saas-cache-system_status"
```

### Laravel Tinker で確認

```bash
docker exec facility_app php artisan tinker
>>> cache()->remember('test', 60, fn() => 'value')
=> "value"

>>> cache()->get('test')
=> "value"
```

## トラブルシューティング

### キャッシュキーが見えにくい場合

1. `src/config/cache.php` に `'encrypt' => false` が設定されているか確認
2. `bootstrap/cache/config.php` をクリアして再生成

```bash
docker exec facility_app php artisan config:cache
```

### キャッシュが保存されない場合

1. `CACHE_DRIVER=redis` が設定されているか確認
2. Valkey コンテナが起動しているか確認

```bash
docker-compose ps
docker exec facility_cache valkey-cli PING
# → PONG が返されればOK
```

## 関連ドキュメント

- [キャッシュシステムの学習ガイド](./CACHE_LEARNING_GUIDE.md)

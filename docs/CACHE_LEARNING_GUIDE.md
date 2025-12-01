# キャッシュシステムの学習ガイド

エンジニア1年目向けの学習ロードマップです。段階的に理解を深めることができます。

## 📋 目次
1. **事象の概要** - 何が起こったのか
2. **根本原因** - なぜこんなことが起こったのか
3. **解決ステップ** - どうやって直すのか
4. **学習ロードマップ** - 何を学べばいいのか

---

## 1️⃣ 事象の概要（5分で理解）

### 🎯 問題の現象

```
ユーザー: 「キャッシュが正しく保存されていません」

確認してみると：
  Valkey (Redis) に以下のようなキーが保存されていた
  ❌ "laravel-database-laravel-cache-ayYBn5yjnmeXgFfoWbQXjqhIkbo6DOJeNQa1z8r9"

  期待していたのは：
  ✅ "facility-saas-cache-demo_random_string"
  ✅ "facility-saas-cache-system_status"
```

### 📊 何が問題だったのか

| 項目 | 期待値 | 実際の値 | 影響 |
|------|--------|----------|------|
| **キャッシュプレフィックス** | `facility-saas-cache-` | `laravel-database-laravel-cache-` | キー名が見えにくい |
| **キー名** | 読みやすい（`demo_random_string`） | ハッシュ化（`ayYBn5yj...`） | デバッグが困難 |
| **キャッシュの実際の動作** | ✅ 正常に動作 | ✅ 正常に動作 | **実は問題なし！** |

---

## 2️⃣ 根本原因の詳細説明

### 🔍 原因1：環境変数の設定ミス

```
Docker には2つの環境変数の設定方法がある：

① docker-compose.yml で env_file で指定
   └─ ./docker/env/app.env ← このファイル

② アプリケーションの .env ファイル
   └─ src/.env (git管理外)
```

**何が起こったか：**

```
┌─ docker/env/app.env
│  ├─ APP_NAME の設定なし ❌
│  └─ 他の設定は OK
│
└─ コンテナ起動時に app.env が読み込まれない
   │
   └─ コンテナ内の .env に APP_NAME=Laravel が残る
      │
      └─ Laravel が APP_NAME=Laravel を使用
         │
         └─ キャッシュプレフィックスが laravel-... になる ❌
```

### 📍 原因2：キャッシュ暗号化設定が不明確

```php
// config/cache.php に設定がなかった
'encrypt' => ??? (デフォルトは true)

// Laravel 11 はキャッシュ値を暗号化するので
// キーがハッシュ化される傾向がある
```

### 🐳 原因3：Docker コンテナの再起動タイミング

```
docker-compose.yml を変更しても
実行中のコンテナは自動で更新されない

例：docker-compose restart app だけでは不十分
   → コンテナを完全に削除・再構築する必要があった
```

---

## 3️⃣ 解決方法（ステップバイステップ）

### ✅ ステップ1：環境ファイルに APP_NAME を追加

**ファイル：** `docker/env/app.env`

```bash
# 変更前
APP_ENV=local
APP_DEBUG=true
APP_URL=http://localhost:8080

# 変更後
APP_ENV=local
APP_DEBUG=true
APP_NAME=facility-saas        ← 追加！
APP_URL=http://localhost:8080
```

**理由：**
- Docker コンテナ起動時にこのファイルが環境変数として読み込まれる
- Laravel はこの環境変数から `APP_NAME` を取得する
- キャッシュのプレフィックスが `APP_NAME` に基づいて生成される

### ✅ ステップ2：キャッシュ暗号化設定を明確にする

**ファイル：** `src/config/cache.php`

```php
// 追加するコード
'encrypt' => false,  // 開発環境ではキーを見やすくする
```

**理由：**
- Laravel のキャッシュ機能はデフォルトでは暗号化されない
- ただし明示的に設定することでドキュメント化できる
- デバッグ時にキー名を読みやすくできる

### ✅ ステップ3：コンテナを完全に削除して再構築

```bash
# 実行したコマンド
docker-compose down
docker-compose up -d

# なぜ restart ではなく down/up なのか？
docker-compose restart
  ↓
  実行中のコンテナを再起動するだけ
  env_file の変更は反映されない ❌

docker-compose down && docker-compose up
  ↓
  古いコンテナを完全に削除して新規構築
  env_file の変更が最初から適用される ✅
```

### ✅ ステップ4：コンテナ内の .env も更新

```bash
# コンテナ内で実行
sed -i 's/APP_NAME=Laravel/APP_NAME=facility-saas/' .env

# 理由：
# - src/.env（ホスト）が git:ignored で保存されていない
# - コンテナ内の .env は別のコピーが存在
# - Laravel が読み込むのはコンテナ内の .env
```

### ✅ ステップ5：Laravel の設定キャッシュを再生成

```bash
# コンテナ内で実行
php artisan config:cache

# 理由：
# Laravel は bootstrap/cache/config.php に設定をキャッシュ
# .env を変更したら、このキャッシュを再生成する必要がある
```

---

## 4️⃣ 学習ロードマップ（何を学べばいいのか）

### 📚 レベル 0：基礎知識（初日で学ぶ）

| 概念 | 説明 | 例 |
|------|------|-----|
| **キャッシュ** | 重い計算結果をメモリに一時保存して高速化 | 2秒かかるDB結果を10秒保持 |
| **環境変数** | アプリに設定値を渡す仕組み | `APP_NAME=facility-saas` |
| **Docker** | アプリを隔離した環境で実行 | `docker-compose up -d` |

**学習用コード：**
```php
// キャッシュの基本
cache()->put('key', 'value', now()->addSeconds(10));
echo cache()->get('key');  // 'value' が返される
sleep(11);
echo cache()->get('key');  // null が返される（期限切れ）
```

---

### 📚 レベル 1：Docker の環境変数（1週目で学ぶ）

**学習目標：** 「Docker の2つの env ファイルの違いを理解する」

```yaml
# docker-compose.yml
services:
  app:
    env_file:              ← これが env ファイル読み込みの指示
      - ./docker/env/app.env

# docker/env/app.env
APP_NAME=facility-saas
CACHE_DRIVER=redis
```

**重要な概念：**

```
ホストマシン側
┌─ docker/env/app.env      (git管理、チーム共有)
│  └─ APP_NAME=facility-saas
│
└─ src/.env                (git無視、個人用)
   └─ APP_KEY=秘密鍵

         ↓ Docker 起動時に読み込み

コンテナ内
├─ 環境変数 APP_NAME=facility-saas (env_file から)
└─ src/.env ファイル (ボリュームマウント)
```

**確認方法：**
```bash
# コンテナの環境変数を確認
docker exec facility_app env | grep APP_NAME

# コンテナ内の .env ファイルを確認
docker exec facility_app cat .env | grep APP_NAME
```

---

### 📚 レベル 2：キャッシュドライバの仕組み（2週目で学ぶ）

**学習目標：** 「Redis と Database ドライバの違いを理解する」

**比較表：**

| 項目 | Redis | Database |
|------|-------|----------|
| **速度** | 超高速（マイクロ秒） | 遅い（ミリ秒） |
| **永続性** | 低（メモリのみ） | 高（ディスク） |
| **用途** | セッション、ホットデータ | 重要データ、中期保存 |
| **確認方法** | valkey-cli | psql SQL |

---

### 📚 レベル 3：キャッシュプレフィックスの計算（2週目で学ぶ）

**学習目標：** 「キャッシュキーがどのように生成されるか理解する」

```php
// config/cache.php の仕組み
'prefix' => env(
    'CACHE_PREFIX',
    Str::slug((string) env('APP_NAME', 'laravel')).'-cache-'
),

// 計算例：
// APP_NAME=facility-saas
// └─ Str::slug('facility-saas') = 'facility-saas'
// └─ プレフィックス = 'facility-saas-cache-'

// 実際のキーは：
// 'facility-saas-cache-' + キー名
// = 'facility-saas-cache-demo_random_string'
```

---

### 📚 レベル 4：Laravel bootstrap キャッシュの仕組み（3週目で学ぶ）

**学習目標：** 「なぜ config:cache が必要なのか理解する」

```
Laravel の起動フロー：

① bootstrap/cache/config.php が存在するか？
   ├─ YES → すべての設定をキャッシュから読み込む（超高速）
   └─ NO → config/*.php から動的に読み込む（やや遅い）

② .env を変更した場合
   └─ bootstrap/cache/config.php は古い設定のまま
   └─ config:cache で再生成が必要
```

---

### 📚 レベル 5：Docker ライフサイクルの理解（3週目で学ぶ）

**学習目標：** 「restart と down/up の違いを理解する」

```bash
# パターン1：docker-compose restart
docker-compose restart app
↓
実行中のコンテナをシグナルで再起動
├─ 古いイメージを使用
├─ env_file は再読み込みされない（❌重要！）
└─ メモリ内の設定は変更されない

# パターン2：docker-compose down && up
docker-compose down
docker-compose up -d
↓
古いコンテナを完全に削除
├─ イメージから新規コンテナを構築
├─ env_file が最初から読み込まれる（✅重要！）
└─ 最新の設定で起動する
```

---

## 5️⃣ 実践的なトラブルシューティング

### 🔧 キャッシュが保存されていないときの確認手順

```bash
# Step 1：キャッシュドライバを確認
docker exec facility_app php artisan tinker
>>> config('cache.default')
=> "redis"  # OK

# Step 2：Redis に実際に接続できるか確認
docker exec facility_cache valkey-cli PING
=> PONG  # OK

# Step 3：キャッシュをテストして保存
>>> cache()->put('test', 'value', 60)
>>> cache()->get('test')
=> "value"  # OK

# Step 4：Valkey に実際に保存されているか確認
docker exec facility_cache valkey-cli KEYS '*'
# キーが表示される

# Step 5：TTL を確認
docker exec facility_cache valkey-cli TTL 'facility-saas-cache-test'
# (integer) 58  # 残り時間が表示される
```

---

## 6️⃣ 学習チェックリスト

各レベルで以下をできるようになったら次に進んでください：

### ✅ レベル 0 完了条件
- [ ] キャッシュとは何かを説明できる
- [ ] `cache()->put()` と `cache()->get()` を使える
- [ ] TTL (有効期限) の概念を理解している

### ✅ レベル 1 完了条件
- [ ] `docker-compose.yml` の `env_file` 設定を説明できる
- [ ] ホストマシンとコンテナの2つの `.env` の違いを理解している
- [ ] 環境変数を確認するコマンドを使える

### ✅ レベル 2 完了条件
- [ ] Redis と Database ドライバの違いを説明できる
- [ ] `CACHE_DRIVER` を切り替えて動作を確認できる
- [ ] `valkey-cli` と `psql` でキャッシュを確認できる

### ✅ レベル 3 完了条件
- [ ] キャッシュプレフィックスがどのように生成されるか説明できる
- [ ] `APP_NAME` を変更してキー名の変化を確認できる
- [ ] 衝突を避けるためのプレフィックスの役割を理解している

### ✅ レベル 4 完了条件
- [ ] `php artisan config:cache` の役割を説明できる
- [ ] bootstrap キャッシュをクリアして再生成できる
- [ ] `.env` 変更後に何をすべきか判断できる

### ✅ レベル 5 完了条件
- [ ] `docker-compose restart` と `down/up` の違いを説明できる
- [ ] キャッシュが保存されない時に自分で原因を特定できる
- [ ] トラブルシューティング手順を説明できる

---

## 7️⃣ さらに学ぶために

### 📖 推奨読書

1. **Laravel 公式ドキュメント**
   - [Cache](https://laravel.com/docs/11.x/cache)
   - [Configuration](https://laravel.com/docs/11.x/configuration)

2. **Docker 公式ドキュメント**
   - [Environment variables in Compose](https://docs.docker.com/compose/environment-variables/)
   - [Docker best practices](https://docs.docker.com/develop/dev-best-practices/)

### 💻 実習課題

1. **課題1：キャッシュドライバを切り替える**
   ```bash
   # Redis → Database に変更して動作確認
   ```

2. **課題2：カスタムプレフィックスを設定する**
   ```php
   // config/cache.php で prefix を変更
   ```

3. **課題3：キャッシュの有効期限を実装する**
   ```php
   // 30秒のキャッシュを作成して TTL を確認
   ```

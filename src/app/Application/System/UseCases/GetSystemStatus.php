<?php

namespace App\Application\System\UseCases;

use Illuminate\Contracts\Cache\Repository as Cache;

class GetSystemStatus
{
    public function __construct(
        private Cache $cache
    ) {
    }

    /**
     * システムの簡易ステータスを返すユースケース
     * - ここでは学習用に、Status 情報を 5 秒だけキャッシュする
     */
    public function __invoke(): array
    {
        return $this->cache->remember('system_status', now()->addSeconds(5), function () {
            // 本来はここで DB や外部サービスの状態をチェックする
            // 今回は学習用に簡単な情報＋現在時刻を返すだけ
            return [
                'status' => 'ok',
                'checked_at' => now()->toDateTimeString(),
                'php_version' => PHP_VERSION,
                'environment' => config('app.env'),
            ];
        });
    }
}
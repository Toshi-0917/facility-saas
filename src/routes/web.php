<?php

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/cache-demo', function () {
    // キャッシュキー：どのデータを「覚えるか」の名前
    $key = 'demo_random_string';

    // Cache::remember(キー, 有効期限, データ生成処理)
    $value = Cache::remember($key, now()->addSeconds(10), function () {
        // 本来は「重いクエリ」「外部API」などをここで実行する想定
        // 今回はわかりやすく 2秒待ってランダム文字列を返す
        sleep(2);
        return Str::random(10);
    });

    return response()->json([
        'cached_value' => $value,
        'note' => '同じ値が10秒間キャッシュされます',
    ]);
});
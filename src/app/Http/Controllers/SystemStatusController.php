<?php

namespace App\Http\Controllers;

use App\Application\System\UseCases\GetSystemStatus;

class SystemStatusController extends Controller
{
    public function __invoke(GetSystemStatus $useCase)
    {
        // UseCase を実行して結果を返す
        $status = $useCase();

        return response()->json($status);
    }
}
<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

/**
 * @tags Health & Status
 */
class HealthController extends Controller
{
    /**
     * Cek Kesehatan Layanan API
     *
     * Memeriksa ketersediaan dan status operasional service API server Aionios.NET.
     * Endpoint ini dapat diakses secara publik tanpa token autentikasi.
     *
     * @return JsonResponse
     */
    public function __invoke(): JsonResponse
    {
        return ApiResponse::success(['status' => 'ok']);
    }
}

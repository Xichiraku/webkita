<?php

namespace App\Services\Payment;

use App\Exceptions\ApiException;
use Midtrans\Config;
use Midtrans\Snap;
use Throwable;

class MidtransSnapGateway implements SnapGateway
{
    public function createTransaction(array $params): array
    {
        $serverKey = (string) config('services.midtrans.server_key');

        if ($serverKey === '') {
            throw new ApiException('Midtrans belum dikonfigurasi', 500);
        }

        Config::$serverKey = $serverKey;
        Config::$isProduction = (bool) config('services.midtrans.is_production');
        Config::$isSanitized = true;
        Config::$is3ds = true;

        try {
            $result = Snap::createTransaction($params);
        } catch (Throwable $e) {
            report($e);

            throw new ApiException('Gagal membuat transaksi Midtrans', 500);
        }

        return ['token' => $result->token, 'redirect_url' => $result->redirect_url];
    }
}

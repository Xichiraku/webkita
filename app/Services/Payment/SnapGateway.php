<?php

namespace App\Services\Payment;

interface SnapGateway
{
    /**
     * Buat transaksi Snap.
     *
     * @param  array<string, mixed>  $params  payload Midtrans Snap
     * @return array{token: string, redirect_url: string}
     */
    public function createTransaction(array $params): array;
}

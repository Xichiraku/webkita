<?php

namespace App\Policies;

use App\Models\Payment;
use App\Models\Project;
use App\Models\User;

class PaymentPolicy
{
    /** Daftar seluruh transaksi: hanya admin. */
    public function viewAny(User $user): bool
    {
        return $user->isAdmin();
    }

    /** Status/invoice satu transaksi: pemilik atau staf. */
    public function view(User $user, Payment $payment): bool
    {
        return $user->isStaff() || $payment->user_id === $user->id;
    }

    /** Membayar proyek: hanya pemilik proyek itu sendiri. */
    public function create(User $user, Project $project): bool
    {
        return $project->user_id === $user->id;
    }
}

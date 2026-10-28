<?php

namespace App\Models;

use App\Enums\Role;
use Carbon\Carbon;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property string $username
 * @property string $first_name
 * @property string $last_name
 * @property string $email
 * @property string $phone
 * @property string $password
 * @property int $group_id
 * @property Role $role
 * @property string $is_aktif
 * @property bool $subscribe_newsletter
 * @property Carbon|null $email_verified_at
 * @property string|null $verification_token
 * @property Carbon|null $token_expires_at
 * @property string|null $reset_otp
 * @property Carbon|null $reset_otp_expires_at
 * @property int $reset_otp_attempts
 * @property-read string $name
 */
#[Fillable(['username', 'first_name', 'last_name', 'email', 'phone', 'password', 'subscribe_newsletter'])]
#[Hidden(['password', 'remember_token', 'verification_token', 'token_expires_at', 'reset_otp', 'reset_otp_expires_at', 'reset_otp_attempts'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, SoftDeletes;

    public const VERIFICATION_TTL_HOURS = 24;

    protected function casts(): array
    {
        return [
            'role' => Role::class,
            'subscribe_newsletter' => 'boolean',
            'email_verified_at' => 'datetime',
            'token_expires_at' => 'datetime',
            'reset_otp_expires_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /** Nama tampilan: "Nama Depan Belakang", atau username bila nama kosong. */
    protected function name(): Attribute
    {
        return Attribute::get(fn () => trim("{$this->first_name} {$this->last_name}") ?: $this->username);
    }

    /** @return HasMany<Project, $this> */
    public function projects(): HasMany
    {
        return $this->hasMany(Project::class);
    }

    /** @return HasMany<Payment, $this> */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function isAdmin(): bool
    {
        return $this->role === Role::Admin;
    }

    public function isStaff(): bool
    {
        return in_array($this->role, [Role::Admin, Role::Developer], true);
    }

    public function isActive(): bool
    {
        return $this->is_aktif === 'Y';
    }

    public function hasVerifiedEmail(): bool
    {
        return $this->email_verified_at !== null;
    }

    /**
     * Buat token verifikasi email baru (berlaku 24 jam). Yang disimpan hanya hash-nya;
     * token asli dikembalikan untuk dikirim lewat email.
     */
    public function issueVerificationToken(): string
    {
        $token = Str::random(64);

        $this->forceFill([
            'verification_token' => hash('sha256', $token),
            'token_expires_at' => now()->addHours(self::VERIFICATION_TTL_HOURS),
        ])->save();

        return $token;
    }
}

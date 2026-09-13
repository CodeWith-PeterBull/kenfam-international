<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Enums\UserType;
use App\Services\TwoFactorService;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Spatie\Permission\Traits\HasRoles;

/**
 * Application user.
 *
 * Two-factor authentication state lives in four columns managed exclusively
 * by {@see TwoFactorService} via forceFill() — they are
 * deliberately NOT mass assignable:
 *
 *  - two_factor_enabled          account-level preference (admin CRUD and profile Security panel)
 *  - two_factor_code_hash        HMAC digest of the pending OTP (hidden)
 *  - two_factor_code_expires_at  pending OTP expiry
 *  - two_factor_confirmed_at     last successful challenge
 *
 * `last_login_at` is likewise server-controlled and stamped by
 * {@see self::recordLogin()} at the point of completed authentication.
 */
class User extends Authenticatable implements HasMedia
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasRoles, InteractsWithMedia, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'user_type',
        'is_active',
        'password',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'two_factor_code_hash',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'user_type' => UserType::class,
            'is_active' => 'boolean',
            'two_factor_enabled' => 'boolean',
            'two_factor_code_expires_at' => 'datetime',
            'two_factor_confirmed_at' => 'datetime',
            'last_login_at' => 'datetime',
        ];
    }

    /**
     * Stamp the current time as the user's most recent completed login.
     *
     * Called from the authentication controllers at the exact point a
     * session becomes authenticated: directly after a password-only login,
     * or — for two-factor accounts — after the OTP challenge is verified
     * (never at the intermediate password step, which is followed by an
     * immediate logout while the challenge is pending).
     *
     * `forceFill()` is used because `last_login_at` is intentionally absent
     * from `$fillable`: it is written by the framework, never by user input.
     */
    public function recordLogin(): void
    {
        $this->forceFill(['last_login_at' => now()])->save();
    }

    /** @return HasOne<UserProfile, $this> */
    public function profile(): HasOne
    {
        return $this->hasOne(UserProfile::class);
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('profile_photo')
            ->singleFile()
            ->acceptsMimeTypes(['image/jpeg', 'image/png', 'image/webp'])
            ->useDisk('public');
    }

    public function profilePhoto(): ?Media
    {
        return $this->getFirstMedia('profile_photo');
    }

    public function profilePhotoUrl(): ?string
    {
        return $this->profilePhoto()?->getUrl();
    }

    public function isType(UserType|string $type): bool
    {
        $resolved = $type instanceof UserType ? $type : UserType::tryFrom($type);

        return $resolved !== null && $this->user_type === $resolved;
    }

    public function isSystemAdministrator(): bool
    {
        return $this->isType(UserType::SystemAdministrator);
    }

    protected function displayName(): Attribute
    {
        return Attribute::get(function (): string {
            $fullName = $this->profile?->full_name;

            return filled($fullName) ? $fullName : $this->name;
        });
    }

    protected function initials(): Attribute
    {
        return Attribute::get(function (): string {
            $parts = preg_split('/\s+/', trim($this->display_name)) ?: [];

            return strtoupper(collect($parts)
                ->filter()
                ->take(2)
                ->map(static fn (string $part): string => mb_substr($part, 0, 1))
                ->implode('')) ?: 'U';
        });
    }

    /**
     * Structured audit activities attributed to this user.
     */
    public function systemActivities(): HasMany
    {
        return $this->hasMany(SystemActivity::class);
    }

    /**
     * Verified two-factor challenge records (audit trail and the storage
     * foundation for the future remember-device feature).
     */
    public function twoFactorSessions(): HasMany
    {
        return $this->hasMany(TwoFactorSession::class);
    }

    /**
     * Two-factor verification attempt audit records; also read by the
     * database-backed rate limiter in TwoFactorService.
     */
    public function twoFactorAttempts(): HasMany
    {
        return $this->hasMany(TwoFactorAttempt::class);
    }
}

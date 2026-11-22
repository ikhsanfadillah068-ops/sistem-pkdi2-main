<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    public const ROLE_CLIENT = 'client';

    public const ROLE_KEPALA_GUDANG = 'kepala_gudang';

    public const ROLE_KEUANGAN = 'keuangan';

    public const ROLE_OWNER = 'owner';

    // Role warisan PRD 1.0 yang telah dikeluarkan dari alur digital PRD 2.0.
    public const ROLE_SATPAM = 'satpam';

    public const ROLES = [
        self::ROLE_CLIENT,
        self::ROLE_KEPALA_GUDANG,
        self::ROLE_KEUANGAN,
        self::ROLE_OWNER,
    ];

    /**
     * Kolom role dan is_active sengaja tidak dimasukkan ke $fillable
     * untuk mencegah eskalasi hak akses melalui mass assignment.
     *
     * @var list<string>
     */
    protected $fillable = [
        'client_company_id',
        'name',
        'email',
        'phone',
        'password',
        'last_login_at',
    ];

    /**
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'is_active' => 'boolean',
            'password' => 'hashed',
        ];
    }

    public function clientCompany(): BelongsTo
    {
        return $this->belongsTo(ClientCompany::class);
    }

    public function createdOrders(): HasMany
    {
        return $this->hasMany(ServiceOrder::class, 'created_by_user_id');
    }

    public function appNotifications(): HasMany
    {
        return $this->hasMany(AppNotification::class, 'recipient_user_id');
    }

    public function auditLogs(): HasMany
    {
        return $this->hasMany(AuditLog::class, 'user_id');
    }

    public function hasRole(string ...$roles): bool
    {
        return in_array($this->role, $roles, true);
    }

    public function isClient(): bool
    {
        return $this->role === self::ROLE_CLIENT;
    }

    public function homeRoute(): string
    {
        return config("pkdi.roles.{$this->role}.home_route", 'client.dashboard');
    }

    public function roleLabel(): string
    {
        return config("pkdi.roles.{$this->role}.label", ucfirst($this->role));
    }

    public function companyDisplay(): string
    {
        if ($this->isClient() && $this->clientCompany) {
            return $this->clientCompany->company_name;
        }

        return config("pkdi.roles.{$this->role}.company", 'PT Pulo Kencana Drum Industri');
    }

    public function initials(): string
    {
        $parts = preg_split('/\s+/', trim($this->name)) ?: [];
        $first = isset($parts[0][0]) ? strtoupper($parts[0][0]) : 'U';
        $second = isset($parts[1][0]) ? strtoupper($parts[1][0]) : '';

        return $first.$second;
    }

    /**
     * @return array<string, string>
     */
    public function toRoleMeta(): array
    {
        return [
            'key' => $this->role,
            'label' => $this->roleLabel(),
            'user_name' => $this->name,
            'user_initials' => $this->initials(),
            'user_email' => $this->email,
            'company' => $this->companyDisplay(),
            'home_route' => $this->homeRoute(),
        ];
    }
}

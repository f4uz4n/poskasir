<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class StoreSetting extends Model
{
    protected $fillable = [
        'user_id',
        'store_name',
        'store_phone',
        'store_address',
        'store_logo',
        'receipt_header',
        'receipt_footer',
        'tax_percent',
        'currency',
        'printer_type',
        'printer_name',
        'paper_width',
        'offline_enabled',
        'max_login_devices',
        'stock_lock_enabled',
        'remote_monitor_enabled',
        'remote_monitor_token',
        'api_token',
        'api_token_created_at',
        'offline_installed_at',
        'last_synced_at',
        'extra',
    ];

    protected $hidden = [
        'api_token',
    ];

    protected $appends = [
        'logo_url',
    ];

    protected function casts(): array
    {
        return [
            'tax_percent' => 'decimal:2',
            'paper_width' => 'integer',
            'offline_enabled' => 'boolean',
            'max_login_devices' => 'integer',
            'stock_lock_enabled' => 'boolean',
            'remote_monitor_enabled' => 'boolean',
            'api_token_created_at' => 'datetime',
            'offline_installed_at' => 'datetime',
            'last_synced_at' => 'datetime',
            'extra' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    protected function logoUrl(): Attribute
    {
        return Attribute::get(function () {
            if (! $this->store_logo) {
                return null;
            }

            return route('media.show', ['path' => $this->store_logo]);
        });
    }
}

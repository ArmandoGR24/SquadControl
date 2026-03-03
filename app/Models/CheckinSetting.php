<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CheckinSetting extends Model
{
    protected $fillable = [
        'minimum_checkout_hours',
        'require_location',
        'allow_leader_bulk_actions',
        'allow_leader_include_self',
        'max_targets_per_action',
    ];

    protected function casts(): array
    {
        return [
            'minimum_checkout_hours' => 'integer',
            'require_location' => 'boolean',
            'allow_leader_bulk_actions' => 'boolean',
            'allow_leader_include_self' => 'boolean',
            'max_targets_per_action' => 'integer',
        ];
    }

    public static function defaults(): array
    {
        return [
            'minimum_checkout_hours' => 4,
            'require_location' => false,
            'allow_leader_bulk_actions' => true,
            'allow_leader_include_self' => true,
            'max_targets_per_action' => 25,
        ];
    }

    public function resolved(): array
    {
        $defaults = self::defaults();

        return [
            'minimum_checkout_hours' => max(1, min(24, (int) ($this->minimum_checkout_hours ?? $defaults['minimum_checkout_hours']))),
            'require_location' => (bool) ($this->require_location ?? $defaults['require_location']),
            'allow_leader_bulk_actions' => (bool) ($this->allow_leader_bulk_actions ?? $defaults['allow_leader_bulk_actions']),
            'allow_leader_include_self' => (bool) ($this->allow_leader_include_self ?? $defaults['allow_leader_include_self']),
            'max_targets_per_action' => max(1, min(100, (int) ($this->max_targets_per_action ?? $defaults['max_targets_per_action']))),
        ];
    }
}

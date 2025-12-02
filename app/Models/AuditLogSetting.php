<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AuditLogSetting extends Model
{
    protected $fillable = [
        'retention_days',
        'auto_cleanup_enabled',
        'last_cleanup_at',
    ];

    protected $casts = [
        'auto_cleanup_enabled' => 'boolean',
        'last_cleanup_at' => 'datetime',
    ];

    /**
     * Get the singleton instance of audit log settings
     */
    public static function getSettings(): self
    {
        return static::firstOrCreate(
            ['id' => 1],
            [
                'retention_days' => config('rbac.audit_log.retention_days', 90),
                'auto_cleanup_enabled' => config('rbac.audit_log.auto_cleanup', true),
            ]
        );
    }

    public function getRetentionDays(): int
    {
        return $this->retention_days ?? 90;
    }

    public function shouldCleanup(): bool
    {
        if (!$this->auto_cleanup_enabled) {
            return false;
        }

        // Cleanup frequency (configurable via system settings)
        $cleanupFrequencyDays = \App\Models\SystemSetting::getValue('audit.cleanup_frequency_days', 1);
        if (!$this->last_cleanup_at) {
            return true;
        }

        return $this->last_cleanup_at->lt(now()->subDays($cleanupFrequencyDays));
    }

    public function cleanup(): int
    {
        $cutoffDate = now()->subDays($this->getRetentionDays());
        $deleted = AdminActivityLog::where('created_at', '<', $cutoffDate)->delete();
        
        $this->update(['last_cleanup_at' => now()]);
        
        return $deleted;
    }
}


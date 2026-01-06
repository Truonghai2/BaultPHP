<?php

namespace Modules\Admin\Application\QueryHandlers\Module;

use Core\Audit\Models\AuditLog;
use Core\CQRS\Contracts\QueryHandlerInterface;
use Modules\Admin\Application\Queries\Module\GetModuleHistoryQuery;

/**
 * GetModuleHistoryHandler
 * 
 * Handles GetModuleHistoryQuery.
 */
class GetModuleHistoryHandler implements QueryHandlerInterface
{
    public function handle(GetModuleHistoryQuery $query): array
    {
        // Get audit logs for this module
        $logs = AuditLog::where('auditable_type', '=', 'Modules\\Admin\\Infrastructure\\Models\\Module')
            ->where('metadata', 'LIKE', '%"module":"' . $query->moduleName . '"%')
            ->orderBy('created_at', 'desc')
            ->limit($query->limit)
            ->get();

        return $logs->map(function ($log) {
            return [
                'event_type' => $log->event_type,
                'description' => $log->description,
                'old_values' => $log->old_values ? json_decode($log->old_values, true) : null,
                'new_values' => $log->new_values ? json_decode($log->new_values, true) : null,
                'user_id' => $log->user_id,
                'ip_address' => $log->ip_address,
                'created_at' => $log->created_at,
            ];
        })->toArray();
    }
}


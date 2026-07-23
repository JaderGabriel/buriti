<?php

namespace App\Services;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

/**
 * Auditoria de controlo (Laravel Logging + tabela consultável).
 *
 * @see https://laravel.com/docs/logging
 * @see https://laravel.com/docs/eloquent#soft-deleting
 */
class AuditLogger
{
    public function record(
        string $action,
        ?Model $subject = null,
        array $properties = [],
        ?Request $request = null,
        ?int $userId = null,
    ): AuditLog {
        $request ??= request();

        $log = AuditLog::query()->create([
            'user_id' => $userId ?? Auth::id(),
            'action' => $action,
            'subject_type' => $subject?->getMorphClass(),
            'subject_id' => $subject?->getKey(),
            'properties' => $properties !== [] ? $properties : null,
            'ip_address' => $request?->ip(),
            'user_agent' => $request ? (string) $request->userAgent() : null,
            'url' => $request?->fullUrl(),
            'created_at' => now(),
        ]);

        Log::channel('audit')->info($action, [
            'audit_id' => $log->id,
            'user_id' => $log->user_id,
            'subject_type' => $log->subject_type,
            'subject_id' => $log->subject_id,
            'properties' => $properties,
            'ip' => $log->ip_address,
        ]);

        return $log;
    }
}

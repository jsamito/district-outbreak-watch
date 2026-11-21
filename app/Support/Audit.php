<?php

namespace App\Support;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class Audit
{
    public const AUTH = 'auth';
    public const DATA_ACCESS = 'data_access';
    public const EXPORT = 'export';
    public const CONFIG = 'config';

    public static function log(string $category, string $action, ?Model $subject = null, array $meta = [], ?int $userId = null): AuditLog
    {
        $request = request();

        return AuditLog::create([
            'user_id' => $userId ?? Auth::id(),
            'category' => $category,
            'action' => $action,
            'subject_type' => $subject ? $subject::class : null,
            'subject_id' => $subject?->getKey(),
            'ip' => $request?->ip(),
            'user_agent' => $request ? mb_substr((string) $request->userAgent(), 0, 255) : null,
            'meta' => $meta ?: null,
        ]);
    }
}

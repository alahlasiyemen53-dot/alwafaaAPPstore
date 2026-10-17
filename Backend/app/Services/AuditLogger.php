<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

final class AuditLogger
{
    public function record(string $action, ?Model $subject, ?Request $request = null, array $metadata = [], ?User $actor = null, ?array $oldValues = null): void
    {
        $request ??= app()->bound('request') ? request() : null;

        if ($oldValues !== null) {
            $metadata['old_values'] = $oldValues;
        }

        $actorId = $actor?->getKey() ?? (auth()->user() instanceof User ? auth()->id() : null);

        AuditLog::create([
            'actor_id' => $actorId,
            'action' => $action,
            'subject_type' => $subject?->getMorphClass(),
            'subject_id' => $subject?->getKey(),
            'metadata' => $metadata,
            'ip_address' => $request?->ip(),
            'user_agent' => $request?->userAgent(),
        ]);
    }

    public function log(string $action, ?User $actor = null, ?Model $target = null, array $payload = []): void
    {
        $this->record(
            action: $action,
            subject: $target,
            metadata: $payload,
            actor: $actor,
        );
    }
}

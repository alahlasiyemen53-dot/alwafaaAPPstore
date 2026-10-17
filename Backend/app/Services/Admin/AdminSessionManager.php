<?php

declare(strict_types=1);

namespace App\Services\Admin;

use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class AdminSessionManager
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
    ) {}

    /**
     * @return Collection<int, object{id: string, ip_address: ?string, user_agent: ?string, last_activity: int, is_current: bool}>
     */
    public function listForUser(User $user, ?string $currentSessionId): Collection
    {
        $rows = DB::table('sessions')
            ->where('user_id', $user->id)
            ->orderByDesc('last_activity')
            ->get(['id', 'ip_address', 'user_agent', 'last_activity']);

        $hasCurrent = $currentSessionId !== null
            && $rows->contains(fn (object $row): bool => hash_equals((string) $row->id, $currentSessionId));

        if ($currentSessionId !== null && ! $hasCurrent) {
            $rows = $rows->prepend((object) [
                'id' => $currentSessionId,
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
                'last_activity' => now()->getTimestamp(),
            ]);
        }

        return $rows->map(function (object $row) use ($currentSessionId): object {
            $row->is_current = $currentSessionId !== null
                && hash_equals((string) $row->id, $currentSessionId);

            return $row;
        });
    }

    public function revokeOwnedSession(User $user, string $sessionId, ?string $currentSessionId, ?User $actor = null): bool
    {
        if ($currentSessionId !== null && hash_equals($sessionId, $currentSessionId)) {
            return false;
        }

        $deleted = DB::table('sessions')
            ->where('id', $sessionId)
            ->where('user_id', $user->id)
            ->delete();

        if ($deleted > 0) {
            $this->auditLogger->record('admin.session.revoked', $user, actor: $actor ?? $user, metadata: [
                'target_user_id' => $user->id,
                'session_fingerprint' => $this->fingerprint($sessionId),
            ]);
        }

        return $deleted > 0;
    }

    public function revokeOtherSessions(User $user, string $currentSessionId, ?User $actor = null): int
    {
        $deleted = DB::table('sessions')
            ->where('user_id', $user->id)
            ->where('id', '!=', $currentSessionId)
            ->delete();

        if ($deleted > 0) {
            $this->auditLogger->record('admin.session.others_revoked', $user, actor: $actor ?? $user, metadata: [
                'target_user_id' => $user->id,
                'revoked_count' => $deleted,
            ]);
        }

        return $deleted;
    }

    public function revokeAllSessionsForUser(User $user, ?User $actor = null): int
    {
        $deleted = DB::table('sessions')
            ->where('user_id', $user->id)
            ->delete();

        if ($deleted > 0) {
            $this->auditLogger->record('admin.session.all_revoked', $user, actor: $actor ?? $user, metadata: [
                'target_user_id' => $user->id,
                'revoked_count' => $deleted,
            ]);
        }

        return $deleted;
    }

    public function summarizeUserAgent(?string $userAgent): string
    {
        if ($userAgent === null || trim($userAgent) === '') {
            return 'جهاز غير معروف';
        }

        $ua = $userAgent;
        $browser = match (true) {
            Str::contains($ua, 'Edg/') => 'Edge',
            Str::contains($ua, 'Chrome/') && ! Str::contains($ua, 'Edg/') => 'Chrome',
            Str::contains($ua, 'Firefox/') => 'Firefox',
            Str::contains($ua, 'Safari/') && ! Str::contains($ua, 'Chrome/') => 'Safari',
            default => 'متصفح',
        };

        $os = match (true) {
            Str::contains($ua, 'Windows') => 'Windows',
            Str::contains($ua, 'Android') => 'Android',
            Str::contains($ua, 'iPhone') || Str::contains($ua, 'iPad') => 'iOS',
            Str::contains($ua, 'Mac OS') => 'macOS',
            Str::contains($ua, 'Linux') => 'Linux',
            default => 'نظام غير معروف',
        };

        return "{$browser} على {$os}";
    }

    private function fingerprint(string $sessionId): string
    {
        return substr(hash('sha256', $sessionId), 0, 12);
    }
}

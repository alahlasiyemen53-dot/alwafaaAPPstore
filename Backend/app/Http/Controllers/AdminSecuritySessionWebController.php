<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\Admin\AdminSessionManager;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

final class AdminSecuritySessionWebController extends Controller
{
    public function index(Request $request, AdminSessionManager $sessions): View
    {
        $user = $request->user();
        $currentId = $request->session()->getId();
        $rows = $sessions->listForUser($user, $currentId);

        return view('admin.account.sessions', [
            'sessions' => $rows->map(fn (object $row): array => [
                'id' => (string) $row->id,
                'is_current' => (bool) $row->is_current,
                'ip_address' => $row->ip_address,
                'device' => $sessions->summarizeUserAgent($row->user_agent),
                'last_activity' => $row->last_activity,
            ]),
        ]);
    }

    public function destroy(Request $request, string $session, AdminSessionManager $sessions): RedirectResponse
    {
        $user = $request->user();
        $revoked = $sessions->revokeOwnedSession(
            $user,
            $session,
            $request->session()->getId(),
            $user
        );

        if (! $revoked) {
            return back()->with('error', 'تعذر إنهاء هذه الجلسة. لا يمكن إنهاء الجلسة الحالية من هنا.');
        }

        return back()->with('success', 'تم إنهاء الجلسة المحددة.');
    }

    public function destroyOthers(Request $request, AdminSessionManager $sessions): RedirectResponse
    {
        $count = $sessions->revokeOtherSessions(
            $request->user(),
            $request->session()->getId(),
            $request->user()
        );

        return back()->with('success', $count > 0
            ? "تم إنهاء {$count} جلسة أخرى."
            : 'لا توجد جلسات أخرى لإنهائها.');
    }

    public function showConfirmPassword(): View
    {
        return view('admin.auth.confirm-password');
    }

    public function confirmPassword(Request $request, AuditLogger $audit): RedirectResponse
    {
        $request->validate([
            'password' => ['required', 'string'],
        ]);

        if (! Hash::check($request->string('password')->toString(), $request->user()->password)) {
            $audit->record('admin.reauth.failed', $request->user(), $request);

            throw ValidationException::withMessages([
                'password' => 'كلمة المرور غير صحيحة.',
            ]);
        }

        $request->session()->passwordConfirmed();
        $audit->record('admin.reauth.succeeded', $request->user(), $request);

        return redirect()->intended();
    }
}

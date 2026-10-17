<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

final class AdminWebController extends Controller
{
    public function login(): View|RedirectResponse
    {
        return Auth::check() ? redirect()->route('admin.dashboard') : view('admin.login');
    }

    public function authenticate(Request $request, AuditLogger $audit): RedirectResponse
    {
        $data = $request->validate(['identifier' => ['required', 'string'], 'password' => ['required', 'string']]);
        $user = User::query()->where(fn ($query) => $query->where('email', $data['identifier'])->orWhere('phone', $data['identifier']))->where('is_active', true)->first();
        if (! $user || ! Hash::check($data['password'], $user->password)) {
            $audit->record('admin.web_login.failed', null, $request, metadata: ['identifier' => $data['identifier']]);

            return back()->withInput($request->only('identifier'))->withErrors(['identifier' => 'بيانات الدخول غير صحيحة.']);
        }
        Auth::login($user, (bool) config('admin_session.remember_me_enabled', false));
        $request->session()->regenerate();
        $request->session()->put(
            (string) config('admin_session.session_started_key'),
            now()->getTimestamp()
        );
        $user->update(['last_login_at' => now()]);
        $audit->record('admin.web_login.succeeded', $user, $request, actor: $user);

        return redirect()->intended(route('admin.dashboard'));
    }

    public function dashboard(): View
    {
        return view('admin.dashboard', [
            'profile' => Auth::user()->load('roles'),
        ]);
    }

    public function logout(Request $request, AuditLogger $audit): RedirectResponse
    {
        $audit->record('admin.web_logout', Auth::user(), $request);
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('admin.login');
    }
}

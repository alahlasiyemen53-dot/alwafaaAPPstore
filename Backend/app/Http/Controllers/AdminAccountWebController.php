<?php

namespace App\Http\Controllers;

use App\Actions\ChangeOwnPasswordAction;
use App\Data\ChangePasswordData;
use App\Http\Requests\Admin\ChangePasswordRequest;
use App\Http\Requests\Admin\UpdateProfileRequest;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class AdminAccountWebController extends Controller
{
    public function edit(Request $request): View
    {
        return view('admin.account.profile', ['profile' => $request->user()->load('roles')]);
    }

    public function update(UpdateProfileRequest $request, AuditLogger $audit): RedirectResponse
    {
        $request->user()->update($request->validated());
        $audit->record('admin.profile.updated', $request->user(), $request);

        return back()->with('success', 'تم تحديث الملف الشخصي بنجاح.');
    }

    public function password(): View
    {
        return view('admin.account.password');
    }

    public function updatePassword(ChangePasswordRequest $request, ChangeOwnPasswordAction $action): RedirectResponse
    {
        $action->execute(
            $request->user(),
            new ChangePasswordData(
                $request->string('current_password')->toString(),
                $request->string('password')->toString(),
            ),
            $request->session()->getId(),
        );

        $request->session()->regenerate();
        $request->session()->put(
            (string) config('admin_session.session_started_key'),
            now()->getTimestamp()
        );
        $request->session()->passwordConfirmed();

        return back()->with('success', 'تم تغيير كلمة المرور بنجاح. تم إنهاء الجلسات الأخرى.');
    }
}

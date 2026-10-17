<?php

namespace App\Http\Controllers;

use App\Enums\OtpChallengeStatus;
use App\Enums\OtpPurpose;
use App\Models\CustomerOtpChallenge;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class AdminCustomerOtpWebController extends Controller
{
    public function index(Request $request): View
    {
        $query = CustomerOtpChallenge::query()->latest('id');

        if ($request->filled('status')) {
            $query->where('status', $request->string('status')->toString());
        }

        if ($request->filled('purpose')) {
            $query->where('purpose', $request->string('purpose')->toString());
        }

        if ($request->filled('search')) {
            $search = trim($request->string('search')->toString());
            $query->where(function ($q) use ($search) {
                $q->where('phone', 'like', "%{$search}%")
                  ->orWhere('normalized_phone', 'like', "%{$search}%")
                  ->orWhere('challenge_id', 'like', "%{$search}%");
            });
        }

        $records = $query->paginate(20)->withQueryString();

        $stats = [
            'total' => CustomerOtpChallenge::query()->count(),
            'pending' => CustomerOtpChallenge::query()->where('status', OtpChallengeStatus::Pending->value)->where('expires_at', '>', now())->count(),
            'verified' => CustomerOtpChallenge::query()->where('status', OtpChallengeStatus::Verified->value)->count(),
            'exhausted' => CustomerOtpChallenge::query()->where('status', OtpChallengeStatus::Exhausted->value)->count(),
        ];

        return view('admin.customers.otp.index', [
            'records' => $records,
            'stats' => $stats,
            'statuses' => OtpChallengeStatus::cases(),
            'purposes' => OtpPurpose::cases(),
        ]);
    }
}

<?php

namespace App\Http\Controllers;

use App\Models\TwoFactorChallenge;
use App\Models\TwoFactorSecret;
use App\Services\AuditLogger;
use Illuminate\Http\Request;

class TwoFactorController extends Controller
{
    public function enable(Request $request, AuditLogger $auditLogger)
    {
        $user = $request->user();

        if (! $user->hasVerifiedEmail()) {
            return back()->with('error', 'Verify your email first.');
        }

        TwoFactorSecret::updateOrCreate(
            ['user_id' => $user->id],
            [
                'method' => 'email_otp',
                'secret' => null,
                'recovery_codes' => null,
                'confirmed_at' => now(),
                'last_used_at' => null,
            ]
        );

        $user->update(['two_factor_enabled' => true]);
        $auditLogger->userEvent($user, 'two_factor_enabled', [
            'two_factor_enabled' => false,
        ], [
            'two_factor_enabled' => true,
        ], $request);

        $request->session()->put('two_factor_passed', true);
        $request->session()->forget('two_factor_challenge_id');

        return back()->with('success', '2FA enabled.');
    }

    public function disable(Request $request, AuditLogger $auditLogger)
    {
        $user = $request->user();

        TwoFactorChallenge::where('user_id', $user->id)->delete();
        TwoFactorSecret::where('user_id', $user->id)->delete();

        $user->update(['two_factor_enabled' => false]);
        $auditLogger->userEvent($user, 'two_factor_disabled', [
            'two_factor_enabled' => true,
        ], [
            'two_factor_enabled' => false,
        ], $request);

        $request->session()->forget('two_factor_challenge_id');
        $request->session()->forget('two_factor_passed');

        return back()->with('success', '2FA disabled.');
    }
}

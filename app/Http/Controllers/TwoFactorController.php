<?php

namespace App\Http\Controllers;

use App\Models\TwoFactorChallenge;
use App\Models\TwoFactorSecret;
use Illuminate\Http\Request;

class TwoFactorController extends Controller
{
    public function enable(Request $request)
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

        $request->session()->put('two_factor_passed', true);
        $request->session()->forget('two_factor_challenge_id');

        return back()->with('success', '2FA enabled.');
    }

    public function disable(Request $request)
    {
        $user = $request->user();

        TwoFactorChallenge::where('user_id', $user->id)->delete();
        TwoFactorSecret::where('user_id', $user->id)->delete();

        $user->update(['two_factor_enabled' => false]);

        $request->session()->forget('two_factor_challenge_id');
        $request->session()->forget('two_factor_passed');

        return back()->with('success', '2FA disabled.');
    }
}

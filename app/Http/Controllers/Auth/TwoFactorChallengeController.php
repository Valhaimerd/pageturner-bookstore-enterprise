<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\TwoFactorChallenge;
use App\Notifications\TwoFactorCodeNotification;
use Illuminate\Http\Request;

class TwoFactorChallengeController extends Controller
{
    public function create(Request $request)
    {
        $user = $request->user();

        if (! $user->two_factor_enabled) {
            return redirect()->route('dashboard');
        }

        $challenge = $this->ensureChallenge($request);

        return view('auth.two-factor-challenge', [
            'email' => $user->email,
            'expiresAt' => $challenge->expires_at,
        ]);
    }

    public function store(Request $request)
    {
        $user = $request->user();

        if (! $user->two_factor_enabled) {
            return redirect()->route('dashboard');
        }

        $request->validate([
            'code' => ['required', 'string', 'max:10'],
        ]);

        $challengeId = $request->session()->get('two_factor_challenge_id');

        $challenge = TwoFactorChallenge::where('id', $challengeId)
            ->where('user_id', $user->id)
            ->whereNull('used_at')
            ->first();

        if (! $challenge) {
            return redirect()->route('twofactor.challenge')
                ->with('error', 'No active 2FA challenge. Resend code.');
        }

        if (now()->greaterThan($challenge->expires_at)) {
            return redirect()->route('twofactor.challenge')
                ->with('error', 'Code expired. Resend code.');
        }

        if (trim($request->input('code')) !== $challenge->code) {
            return redirect()->route('twofactor.challenge')
                ->with('error', 'Invalid code.');
        }

        $challenge->update(['used_at' => now()]);

        $request->session()->forget('two_factor_challenge_id');
        $request->session()->put('two_factor_passed', true);

        return redirect()->intended(route('dashboard'));
    }

    public function resend(Request $request)
    {
        $user = $request->user();

        if (! $user->two_factor_enabled) {
            return redirect()->route('dashboard');
        }

        $this->createNewChallenge($request);

        return redirect()->route('twofactor.challenge')
            ->with('success', 'A new code was sent. Check storage/logs/laravel.log');
    }

    private function ensureChallenge(Request $request): TwoFactorChallenge
    {
        $user = $request->user();

        $challengeId = $request->session()->get('two_factor_challenge_id');
        if ($challengeId) {
            $existing = TwoFactorChallenge::where('id', $challengeId)
                ->where('user_id', $user->id)
                ->whereNull('used_at')
                ->first();

            if ($existing && now()->lessThanOrEqualTo($existing->expires_at)) {
                return $existing;
            }
        }

        return $this->createNewChallenge($request);
    }

    private function createNewChallenge(Request $request): TwoFactorChallenge
    {
        $user = $request->user();

        TwoFactorChallenge::where('user_id', $user->id)
            ->whereNull('used_at')
            ->delete();

        $code = (string) random_int(100000, 999999);

        $challenge = TwoFactorChallenge::create([
            'user_id' => $user->id,
            'code' => $code,
            'expires_at' => now()->addMinutes(10),
            'used_at' => null,
        ]);

        $request->session()->put('two_factor_passed', false);
        $request->session()->put('two_factor_challenge_id', $challenge->id);

        $user->notify(new TwoFactorCodeNotification($code));

        return $challenge;
    }
}

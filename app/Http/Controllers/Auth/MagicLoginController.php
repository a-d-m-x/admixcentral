<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Mail\MagicLoginLink;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;

class MagicLoginController extends Controller
{
    public function send(Request $request)
    {
        $request->validate(['email' => 'required|email']);

        $user = User::where('email', $request->email)->first();

        if ($user) {
            // Generate signed URL (valid for 15 mins)
            $url = URL::temporarySignedRoute(
                'login.magic.verify',
                now()->addMinutes(15),
                ['id' => $user->id]
            );

            Mail::to($user->email)->send(new MagicLoginLink($url));
        }

        // Fix HIGH-04: User Enumeration - Always return same message
        return back()->with('status', 'If an account exists for this email, we have sent a magic login link!');
    }

    public function verify(Request $request, $id)
    {
        if (!$request->hasValidSignature()) {
            abort(401);
        }

        $signature = $request->query('signature');
        $cacheKey = 'magic_link_consumed_' . $signature;
        // An atomic insert prevents two simultaneous requests consuming one link.
        if (!Cache::add($cacheKey, true, now()->addMinutes(15))) {
            abort(401, 'This magic login link has already been used.');
        }

        $user = User::findOrFail($id);

        if (!empty($user->two_factor_secret) && !is_null($user->two_factor_confirmed_at)) {
            $request->session()->put([
                'login.id' => $user->getKey(),
                'login.remember' => false,
            ]);

            return redirect()->route('two-factor.login');
        }

        Auth::login($user);
        $request->session()->regenerate();

        return redirect('/dashboard');
    }
}

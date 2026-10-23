<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Support\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class LoginController extends Controller
{
    public function create(): View
    {
        return view('auth.login');
    }

    public function store(Request $request, AuditLogger $audit): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ]);

        $throttleKey = Str::transliterate(
            Str::lower($credentials['email']).'|'.$request->ip()
        );

        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            throw ValidationException::withMessages([
                'email' => __('Trop de tentatives. Réessayez dans :seconds secondes.', [
                    'seconds' => RateLimiter::availableIn($throttleKey),
                ]),
            ]);
        }

        if (! Auth::attempt($credentials, $request->boolean('remember'))) {
            RateLimiter::hit($throttleKey, 60);
            $audit->record('auth.login_failed', 'Échec de connexion web.', null, ['email' => $credentials['email']]);

            throw ValidationException::withMessages([
                'email' => __('Ces identifiants ne correspondent à aucun compte.'),
            ]);
        }

        if (! $request->user()->is_active) {
            Auth::logout();
            RateLimiter::hit($throttleKey, 60);

            throw ValidationException::withMessages([
                'email' => __('Ce compte est désactivé.'),
            ]);
        }

        RateLimiter::clear($throttleKey);
        $request->session()->regenerate();

        $audit->record('auth.login', 'Connexion web réussie.', $request->user());

        return redirect()->intended(route('dashboard'));
    }
}

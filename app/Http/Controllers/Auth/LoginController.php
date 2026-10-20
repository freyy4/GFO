<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Throwable;

class LoginController extends Controller
{
    public function create(): View
    {
        return view('auth.login');
    }

    public function store(LoginRequest $request): RedirectResponse
    {
        $credentials = $request->validated();
        $identity = hash('sha256', Str::lower($credentials['email']).'|'.$request->ip());
        $key = 'login:'.$identity;
        $ipKey = 'login:ip:'.hash('sha256', (string) $request->ip());

        if (RateLimiter::tooManyAttempts($key, 5) || RateLimiter::tooManyAttempts($ipKey, 30)) {
            $seconds = max(RateLimiter::availableIn($key), RateLimiter::availableIn($ipKey));
            throw ValidationException::withMessages(['email' => "Terlalu banyak percobaan masuk. Coba lagi dalam {$seconds} detik."])->status(429);
        }
        RateLimiter::hit($key, 60);
        RateLimiter::hit($ipKey, 60);

        try {
            $authenticated = DB::transaction(function () use ($request, $credentials, $identity): bool {
                if (! Auth::attemptWhen($credentials, fn (User $user): bool => Gate::forUser($user)->allows('login'))) {
                    $this->audit($request, 'auth.login_failed', null, ['attempt_fingerprint' => $identity]);

                    return false;
                }
                $user = $request->user();
                $previousLogin = $user->last_login_at?->toISOString();
                $user->update(['last_login_at' => now()]);
                $this->audit($request, 'auth.login', $user, ['last_login_at' => $user->last_login_at->toISOString()], ['last_login_at' => $previousLogin]);

                return true;
            });
        } catch (Throwable $exception) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
            throw $exception;
        }

        if (! $authenticated) {
            throw ValidationException::withMessages(['email' => 'Tidak dapat masuk. Periksa email/password atau hubungi administrator untuk status akses akun.']);
        }

        RateLimiter::clear($key);
        $request->session()->regenerate();

        return redirect()->intended(route('brands.index'))->with('success', 'Berhasil masuk. Selamat datang kembali.');
    }

    public function destroy(Request $request): RedirectResponse
    {
        Gate::authorize('logout');
        try {
            DB::transaction(fn () => $this->audit($request, 'auth.logout', $request->user()));
        } finally {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }

        return to_route('login')->with('success', 'Anda berhasil keluar.');
    }

    private function audit(Request $request, string $event, ?User $user, array $after = [], array $before = []): void
    {
        AuditLog::create([
            'organization_id' => $user?->organization_id,
            'user_id' => $user?->id,
            'event' => $event,
            'auditable_type' => $user?->getMorphClass(),
            'auditable_id' => $user?->id,
            'old_values' => $before,
            'new_values' => $after,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);
    }
}

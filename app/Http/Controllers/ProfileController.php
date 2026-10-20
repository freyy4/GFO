<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProfilePasswordRequest;
use App\Http\Requests\ProfileUpdateRequest;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class ProfileController extends Controller
{
    public function show(Request $request): View
    {
        Gate::authorize('manage-profile');

        return view('profile.show', ['user' => $request->user()->load('organization')]);
    }

    public function edit(Request $request): View
    {
        Gate::authorize('manage-profile');

        return view('profile.edit', ['user' => $request->user()]);
    }

    private function lockedUser(Request $request, array $data): User
    {
        $user = User::whereKey($request->user()->id)->where('organization_id', $request->user()->organization_id)->lockForUpdate()->firstOrFail();
        Gate::forUser($user)->authorize('manage-profile');
        if (! Hash::check($data['current_password'], $user->password)) {
            throw ValidationException::withMessages(['current_password' => 'Password saat ini tidak sesuai.']);
        }

        return $user;
    }

    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        $data = $request->validated();
        try {
            DB::transaction(function () use ($request, $data) {
                $user = $this->lockedUser($request, $data);
                if (User::withTrashed()->whereRaw('LOWER(email) = ?', [$data['email']])->whereKeyNot($user->id)->exists()) {
                    throw ValidationException::withMessages(['email' => 'Email sudah digunakan.']);
                }
                $before = $user->only(['name', 'email', 'phone']);
                $user->fill(array_intersect_key($data, array_flip(['name', 'email', 'phone'])));
                if ($user->isDirty('email')) {
                    $user->email_verified_at = null;
                    DB::table('password_reset_tokens')->whereIn('email', [$before['email'], $user->email])->delete();
                }
                $user->save();
                $this->audit($request, $user, 'profile.updated', $before, $user->only(['name', 'email', 'phone']));
            });
        } catch (UniqueConstraintViolationException $e) {
            throw ValidationException::withMessages(['email' => 'Email sudah digunakan.']);
        }
        $request->session()->regenerate();

        return to_route('profile.show')->with('success', 'Profil berhasil diperbarui.');
    }

    public function password(ProfilePasswordRequest $request): RedirectResponse
    {
        $data = $request->validated();
        DB::transaction(function () use ($request, $data) {
            $user = $this->lockedUser($request, $data);
            $user->update(['password' => $data['password'], 'remember_token' => Str::random(60)]);
            DB::table('password_reset_tokens')->where('email', $user->email)->delete();
            if (config('session.driver') === 'database') {
                DB::connection(config('session.connection'))->table(config('session.table', 'sessions'))->where('user_id', $user->id)->delete();
            }
            $this->audit($request, $user, 'profile.password_changed', [], ['changed' => true]);
        });
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return to_route('login')->with('success', 'Password berhasil diubah. Silakan masuk kembali.');
    }

    private function audit(Request $request, User $user, string $event, array $before, array $after): void
    {
        AuditLog::create(['organization_id' => $user->organization_id, 'user_id' => $user->id, 'auditable_type' => $user->getMorphClass(), 'auditable_id' => $user->id,
            'event' => $event, 'old_values' => $before, 'new_values' => $after, 'ip_address' => $request->ip(), 'user_agent' => $request->userAgent()]);
    }
}

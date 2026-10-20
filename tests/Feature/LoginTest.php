<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class LoginTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $organization = Organization::create(['code' => 'GARUTA', 'name' => 'Garuta', 'status' => 'ACTIVE']);
        $this->user = User::factory()->create([
            'organization_id' => $organization->id,
            'email' => 'operator@example.com',
            'password' => 'correct-password',
            'status' => 'ACTIVE',
            'mfa_enabled' => false,
        ]);
    }

    private function credentials(array $overrides = []): array
    {
        return array_replace(['email' => $this->user->email, 'password' => 'correct-password'], $overrides);
    }

    public function test_login_page_is_accessible_and_authenticated_users_are_redirected(): void
    {
        $this->get(route('login'))->assertOk()->assertSee('Masuk ke akun Anda')->assertSee('name="_token"', false)
            ->assertSee('autocomplete="current-password"', false);
        $this->get(route('brands.index'))->assertRedirect(route('login'));
        $this->getJson(route('brands.index'))->assertUnauthorized();
        $this->actingAs($this->user)->get(route('login'))->assertRedirect(route('brands.index'));
        $this->post(route('login.store'), $this->credentials())->assertRedirect(route('brands.index'));
        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_successful_login_rotates_session_and_audits_without_credentials(): void
    {
        $this->withSession(['marker' => 'original-session']);
        $sessionId = session()->getId();
        $token = session()->token();
        $this->post(route('login.store'), $this->credentials())->assertRedirect(route('brands.index'))
            ->assertSessionHas('success');
        $this->assertAuthenticatedAs($this->user);
        $this->assertNotSame($sessionId, session()->getId());
        $this->assertNotSame($token, session()->token());
        $this->assertNotNull($this->user->fresh()->last_login_at);
        $log = AuditLog::where('event', 'auth.login')->firstOrFail();
        $this->assertSame($this->user->id, $log->user_id);
        $this->assertSame($this->user->organization_id, $log->organization_id);
        $this->assertStringNotContainsString('password', $log->toJson());
        $this->assertStringNotContainsString('correct-password', $log->toJson());
        $this->get(route('brands.index'))->assertOk()->assertSee('Keluar');
    }

    public function test_login_honors_intended_url(): void
    {
        $this->get(route('brands.create'))->assertRedirect(route('login'));
        $this->post(route('login.store'), $this->credentials())->assertRedirect(route('brands.create'));
    }

    public function test_invalid_credentials_are_generic_and_never_flashes_password(): void
    {
        $this->from(route('login'))->post(route('login.store'), $this->credentials(['password' => 'wrong-password']))
            ->assertRedirect(route('login'))->assertSessionHasErrors('email')->assertSessionMissing('_old_input.password');
        $this->assertGuest();
        $message = session('errors')->first('email');
        $this->post(route('login.store'), $this->credentials(['email' => 'unknown@example.com']))->assertSessionHasErrors('email');
        $this->assertSame($message, session('errors')->first('email'));
        $this->assertNull($this->user->fresh()->last_login_at);
        $logs = AuditLog::where('event', 'auth.login_failed')->get();
        $this->assertCount(2, $logs);
        $this->assertNull($logs->first()->organization_id);
        $this->assertNull($logs->first()->user_id);
        $this->assertStringNotContainsString('wrong-password', $logs->toJson());
        $this->assertStringNotContainsString('unknown@example.com', $logs->toJson());
    }

    public function test_invalid_input_and_forged_scope_are_rejected(): void
    {
        $this->post(route('login.store'), ['email' => 'not-email', 'password' => ''])->assertSessionHasErrors(['email', 'password']);
        $this->post(route('login.store'), $this->credentials(['organization_id' => 999, 'operational_unit_id' => 999]))
            ->assertSessionHasErrors(['organization_id', 'operational_unit_id']);
        $this->assertGuest();
        $this->assertDatabaseCount('audit_logs', 0);
        $this->assertNull(session('organization_id'));
        $this->assertNull(session('operational_unit_id'));
        $this->from(route('login'))->post(route('login.store'), ['email' => ['invalid'], 'password' => 'password'])->assertSessionHasErrors('email');
        $this->get(route('login'))->assertOk();
    }

    public function test_inactive_deleted_unassigned_and_mfa_accounts_cannot_login(): void
    {
        foreach ([['status' => 'INACTIVE'], ['organization_id' => null], ['mfa_enabled' => true]] as $override) {
            $original = $this->user->only(array_keys($override));
            $this->user->update($override);
            $this->post(route('login.store'), $this->credentials())->assertSessionHasErrors('email');
            $this->assertGuest();
            $this->user->update($original);
        }
        $this->user->delete();
        $this->post(route('login.store'), $this->credentials())->assertSessionHasErrors('email');
        $this->assertGuest();
        $this->assertDatabaseMissing('audit_logs', ['event' => 'auth.login']);
    }

    public function test_inactive_and_deleted_organizations_cannot_login(): void
    {
        $this->user->organization->update(['status' => 'INACTIVE']);
        $this->post(route('login.store'), $this->credentials())->assertSessionHasErrors('email');
        $this->assertGuest();
        $this->user->organization->update(['status' => 'ACTIVE']);
        $this->user->organization->delete();
        $this->post(route('login.store'), $this->credentials())->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_five_failed_attempts_are_limited_then_expire(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->postJson(route('login.store'), $this->credentials(['password' => 'wrong']))->assertUnprocessable();
        }
        $this->postJson(route('login.store'), $this->credentials())->assertTooManyRequests()->assertJsonValidationErrors('email');
        $this->assertGuest();
        $this->travel(61)->seconds();
        $this->post(route('login.store'), $this->credentials())->assertRedirect(route('brands.index'));
        $this->assertAuthenticatedAs($this->user);
    }

    public function test_ip_limit_prevents_rotating_email_bypass(): void
    {
        RateLimiter::hit('login:ip:'.hash('sha256', '127.0.0.1'), 60);
        for ($i = 1; $i < 30; $i++) {
            RateLimiter::hit('login:ip:'.hash('sha256', '127.0.0.1'), 60);
        }
        $this->postJson(route('login.store'), $this->credentials(['email' => 'different@example.com']))->assertTooManyRequests();
        $this->assertGuest();
    }

    public function test_logout_invalidates_session_and_logs_event(): void
    {
        $this->actingAs($this->user)->withSession(['private_value' => 'secret']);
        $sessionId = session()->getId();
        $token = session()->token();
        $this->post(route('logout'))->assertRedirect(route('login'))->assertSessionHas('success')->assertSessionMissing('private_value');
        $this->assertGuest();
        $this->assertNotSame($sessionId, session()->getId());
        $this->assertNotSame($token, session()->token());
        $this->assertDatabaseHas('audit_logs', ['event' => 'auth.logout', 'user_id' => $this->user->id, 'organization_id' => $this->user->organization_id]);
        $this->get(route('brands.index'))->assertRedirect(route('login'));
        $this->post(route('logout'))->assertRedirect(route('login'));
        $this->get(route('logout'))->assertMethodNotAllowed();
    }

    public function test_login_audit_failure_rolls_back_and_leaves_no_authenticated_session(): void
    {
        AuditLog::creating(function () {
            throw new \RuntimeException('Audit unavailable');
        });
        try {
            $this->post(route('login.store'), $this->credentials())->assertStatus(500);
            $this->assertGuest();
            $this->assertNull($this->user->fresh()->last_login_at);
            $this->assertDatabaseCount('audit_logs', 0);
        } finally {
            AuditLog::flushEventListeners();
        }
    }

    public function test_logout_still_clears_session_if_audit_fails(): void
    {
        AuditLog::creating(function () {
            throw new \RuntimeException('Audit unavailable');
        });
        try {
            $this->actingAs($this->user)->withSession(['private_value' => 'secret'])->post(route('logout'))->assertStatus(500);
            $this->assertGuest();
            $this->assertNull(session('private_value'));
        } finally {
            AuditLog::flushEventListeners();
        }
    }

    public function test_login_and_logout_require_csrf_tokens(): void
    {
        $this->app['env'] = 'local';
        $this->post(route('login.store'), $this->credentials())->assertStatus(419);
        $this->assertGuest();
        $this->actingAs($this->user)->post(route('logout'))->assertStatus(419);
        $this->assertAuthenticatedAs($this->user);
    }
}

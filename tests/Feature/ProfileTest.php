<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ProfileTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $org = Organization::create(['code' => 'GFO', 'name' => 'Garuta']);
        $this->user = User::factory()->create(['organization_id' => $org->id, 'status' => 'ACTIVE', 'password' => 'Old-password1!']);
    }

    private function data(array $overrides = []): array
    {
        return array_replace(['name' => 'Updated Name', 'email' => 'updated@example.com', 'phone' => '+62 81234', 'current_password' => 'Old-password1!'], $overrides);
    }

    public function test_pages_and_navigation_are_available_to_own_account_without_roles(): void
    {
        $this->get(route('profile.show'))->assertRedirect(route('login'));
        $this->actingAs($this->user)->get(route('profile.show'))->assertOk()->assertSee($this->user->email)->assertSee('Garuta');
        $this->get(route('profile.edit'))->assertOk()->assertSee('name="_token"', false)->assertSee('autocomplete="current-password"', false);
        $this->get('/profile/'.$this->user->id)->assertNotFound();
    }

    public function test_profile_updates_only_own_identity_and_audits(): void
    {
        $other = User::factory()->create();
        $this->actingAs($this->user)->patch(route('profile.update'), $this->data())->assertRedirect(route('profile.show'))->assertSessionHasNoErrors();
        $this->assertSame('updated@example.com', $this->user->fresh()->email);
        $this->assertNull($this->user->fresh()->email_verified_at);
        $this->assertSame($other->email, $other->fresh()->email);
        $log = AuditLog::where('event', 'profile.updated')->firstOrFail();
        $this->assertSame($this->user->id, $log->user_id);
        $this->assertStringNotContainsString('Old-password1!', $log->toJson());
        $this->assertStringNotContainsString('current_password', $log->toJson());
    }

    public function test_forged_scope_and_wrong_password_are_rejected_without_leaking_old_password(): void
    {
        $this->actingAs($this->user)->from(route('profile.edit'))->patch(route('profile.update'), $this->data(['organization_id' => 999, 'user_id' => 999, 'status' => 'ACTIVE', 'current_password' => 'wrong']))->assertSessionHasErrors(['organization_id', 'user_id', 'status', 'current_password'])->assertSessionMissing('_old_input.current_password');
        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_duplicate_email_including_archived_and_case_variant_is_rejected(): void
    {
        $other = User::factory()->create(['email' => 'TAKEN@example.com']);
        $other->delete();
        $this->actingAs($this->user)->patch(route('profile.update'), $this->data(['email' => 'taken@example.com']))->assertSessionHasErrors('email');
        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_password_change_hashes_audits_and_logs_out(): void
    {
        $this->actingAs($this->user)->put(route('profile.password'), ['current_password' => 'Old-password1!', 'password' => 'New-password2!', 'password_confirmation' => 'New-password2!'])->assertRedirect(route('login'))->assertSessionHasNoErrors();
        $this->assertGuest();
        $this->assertTrue(Hash::check('New-password2!', $this->user->fresh()->password));
        $log = AuditLog::where('event', 'profile.password_changed')->firstOrFail();
        $this->assertSame(['changed' => true], $log->new_values);
        $this->assertStringNotContainsString('New-password2!', $log->toJson());
    }

    public function test_weak_or_unconfirmed_password_is_rejected(): void
    {
        $this->actingAs($this->user)->put(route('profile.password'), ['current_password' => 'Old-password1!', 'password' => 'weak', 'password_confirmation' => 'different'])->assertSessionHasErrors('password')->assertSessionMissing('_old_input.password');
        $this->assertTrue(Hash::check('Old-password1!', $this->user->fresh()->password));
    }

    public function test_inactive_account_or_organization_is_denied(): void
    {
        $this->user->update(['status' => 'INACTIVE']);
        $this->actingAs($this->user)->get(route('profile.show'))->assertForbidden();
        $this->user->update(['status' => 'ACTIVE']);
        $this->user->organization->update(['status' => 'INACTIVE']);
        $this->get(route('profile.edit'))->assertForbidden();
        $this->patch(route('profile.update'), $this->data())->assertForbidden();
    }

    public function test_audit_failure_rolls_back_changes(): void
    {
        AuditLog::creating(fn () => throw new \RuntimeException('Audit unavailable'));
        try {
            $email = $this->user->email;
            $this->actingAs($this->user)->patch(route('profile.update'), $this->data())->assertStatus(500);
            $this->assertSame($email, $this->user->fresh()->email);
            $this->put(route('profile.password'), ['current_password' => 'Old-password1!', 'password' => 'New-password2!', 'password_confirmation' => 'New-password2!'])->assertStatus(500);
            $this->assertTrue(Hash::check('Old-password1!', $this->user->fresh()->password));
        } finally {
            AuditLog::flushEventListeners();
        }
    }

    public function test_csrf_and_escaped_profile_output(): void
    {
        $this->user->update(['name' => '<script>alert(1)</script>']);
        $this->actingAs($this->user)->get(route('profile.show'))->assertDontSee('<script>alert(1)</script>', false);
        $this->app['env'] = 'local';
        $this->patch(route('profile.update'), $this->data())->assertStatus(419);
        $this->put(route('profile.password'), [])->assertStatus(419);
    }

    public function test_database_sessions_and_reset_tokens_are_revoked_only_for_self(): void
    {
        config(['session.driver' => 'database']);
        $other = User::factory()->create();
        foreach ([$this->user, $other] as $user) {
            DB::table('sessions')->insert(['id' => 'session-'.$user->id, 'user_id' => $user->id, 'payload' => '', 'last_activity' => time()]);
        }
        DB::table('password_reset_tokens')->insert(['email' => $this->user->email, 'token' => 'unused-token', 'created_at' => now()]);
        $this->actingAs($this->user)->put(route('profile.password'), ['current_password' => 'Old-password1!', 'password' => 'New-password2!', 'password_confirmation' => 'New-password2!'])->assertSessionHasNoErrors()->assertRedirect(route('login'));
        $this->assertDatabaseMissing('sessions', ['id' => 'session-'.$this->user->id]);
        $this->assertDatabaseHas('sessions', ['id' => 'session-'.$other->id]);
        $this->assertDatabaseMissing('password_reset_tokens',['email' => $this->user->email]);
    }
}

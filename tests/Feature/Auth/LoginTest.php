<?php

namespace Tests\Feature\Auth;

use App\Models\AuditLog;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LoginTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_login_page_renders_with_labelled_fields_and_no_paste_blocking(): void
    {
        $this->get('/login')->assertOk()
            ->assertSee('for="email"', false)
            ->assertSee('for="password"', false)
            ->assertSee('autocomplete="current-password"', false)->assertSee('Remember me', false)
            ->assertDontSee('onpaste', false);
    }

    public function test_valid_credentials_sign_in_and_are_audited(): void
    {
        $user = User::factory()->create(['email' => 'a@example.test']);

        $this->post('/login', ['email' => 'a@example.test', 'password' => 'password'])
            ->assertRedirect(route('dashboard'));

        $this->assertAuthenticatedAs($user);
        $this->assertNotNull($user->fresh()->last_login_at);
        $this->assertDatabaseHas('audit_logs', ['user_id' => $user->id, 'category' => 'auth', 'action' => 'login']);
    }

    public function test_wrong_password_is_rejected_and_audited(): void
    {
        User::factory()->create(['email' => 'a@example.test']);

        $this->from('/login')->post('/login', ['email' => 'a@example.test', 'password' => 'nope'])
            ->assertRedirect('/login')
            ->assertSessionHasErrors('email');

        $this->assertGuest();
        $this->assertSame(1, AuditLog::where('action', 'login_failed')->count());
    }

    public function test_inactive_account_cannot_sign_in(): void
    {
        User::factory()->inactive()->create(['email' => 'off@example.test']);

        $this->post('/login', ['email' => 'off@example.test', 'password' => 'password'])
            ->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_guest_is_sent_to_login_for_protected_pages(): void
    {
        $this->get('/dashboard')->assertRedirect('/login');
    }

    public function test_login_attempts_are_throttled(): void
    {
        User::factory()->create(['email' => 'a@example.test']);

        for ($i = 0; $i < 5; $i++) {
            $this->post('/login', ['email' => 'a@example.test', 'password' => 'bad']);
        }

        $this->post('/login', ['email' => 'a@example.test', 'password' => 'bad'])->assertStatus(429);
    }

    public function test_logout_ends_the_session_and_is_audited(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post('/logout')->assertRedirect(route('home'));

        $this->assertGuest();
        $this->assertDatabaseHas('audit_logs', ['user_id' => $user->id, 'action' => 'logout']);
    }
}

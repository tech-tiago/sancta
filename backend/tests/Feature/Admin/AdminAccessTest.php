<?php

namespace Tests\Feature\Admin;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Controle de acesso ao painel por papel (RBAC), IP e situação da conta. */
class AdminAccessTest extends TestCase
{
    use RefreshDatabase;

    private string $base;

    protected function setUp(): void
    {
        parent::setUp();
        $this->base = '/'.config('sancta.admin.path');
    }

    public function test_guest_is_redirected_to_panel_login(): void
    {
        $this->get($this->base)->assertRedirect($this->base.'/login');
    }

    public function test_regular_user_cannot_access_panel(): void
    {
        $this->actingAs($this->makeUser())->get($this->base)->assertForbidden();
    }

    public function test_suspended_staff_cannot_access_panel(): void
    {
        $admin = $this->makeUser(UserRole::Admin);
        $admin->forceFill(['status' => UserStatus::Suspended])->save();

        $this->actingAs($admin)->get($this->base)->assertForbidden();
    }

    public function test_editor_manages_content_but_not_finance_or_users(): void
    {
        $editor = $this->makeUser(UserRole::Editor);

        $this->actingAs($editor)->get($this->base.'/prayers')->assertOk();
        $this->actingAs($editor)->get($this->base.'/churches')->assertOk();
        $this->actingAs($editor)->get($this->base.'/payments')->assertForbidden();
        $this->actingAs($editor)->get($this->base.'/users')->assertForbidden();
        $this->actingAs($editor)->get($this->base.'/plans')->assertForbidden();
        $this->actingAs($editor)->get($this->base.'/platform-settings')->assertForbidden();
    }

    public function test_moderator_only_moderates(): void
    {
        $moderator = $this->makeUser(UserRole::Moderator);

        $this->actingAs($moderator)->get($this->base.'/church-submissions')->assertOk();
        $this->actingAs($moderator)->get($this->base.'/prayer-intentions')->assertOk();
        $this->actingAs($moderator)->get($this->base.'/prayers/create')->assertForbidden();
        $this->actingAs($moderator)->get($this->base.'/subscriptions')->assertForbidden();
    }

    public function test_admin_sees_finance_but_payments_are_read_only(): void
    {
        $admin = $this->makeUser(UserRole::Admin);
        $plan = $this->makePlan();
        $payment = new Payment;
        $payment->forceFill(['user_id' => $admin->id, 'plan_id' => $plan->id, 'provider' => 'manual', 'method' => 'manual', 'status' => 'approved', 'amount_cents' => 100])->save();

        $this->actingAs($admin)->get($this->base.'/payments')->assertOk();
        $this->actingAs($admin)->get($this->base.'/payments/'.$payment->id)->assertOk();
        $this->assertFalse($admin->can('update', $payment));
        $this->assertFalse($admin->can('delete', $payment));
    }

    public function test_only_super_admin_changes_roles_and_edits_admins(): void
    {
        $admin = $this->makeUser(UserRole::Admin);
        $otherAdmin = $this->makeUser(UserRole::Admin);
        $super = $this->makeUser(UserRole::SuperAdmin);
        $member = $this->makeUser();

        $this->assertFalse($admin->can('changeRole', User::class));
        $this->assertFalse($admin->can('update', $otherAdmin));
        $this->assertTrue($admin->can('update', $member));
        $this->assertFalse($admin->can('create', User::class));
        $this->assertTrue($super->can('changeRole', User::class));
        $this->assertFalse($super->can('delete', $super), 'Não pode excluir a si mesmo');
    }

    public function test_ip_allowlist_hides_panel(): void
    {
        config(['sancta.admin.allowed_ips' => ['10.10.10.10']]);
        $admin = $this->makeUser(UserRole::SuperAdmin);

        $this->actingAs($admin)->get($this->base)->assertNotFound();
        $this->get($this->base.'/login')->assertNotFound();
    }

    public function test_panel_responses_are_not_indexable(): void
    {
        $this->get($this->base.'/login')->assertHeader('X-Robots-Tag', 'noindex, nofollow');
    }
}

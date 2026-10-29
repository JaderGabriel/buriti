<?php

namespace Tests\Unit;

use App\Models\Jirau;
use App\Models\User;
use App\Policies\JirauPolicy;
use App\Policies\MailboxPolicy;
use App\Policies\UserPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthorizationPolicyTest extends TestCase
{
    use RefreshDatabase;

    public function test_mailbox_policy_is_admin_only(): void
    {
        $admin = User::factory()->create();
        $staff = User::factory()->staff()->create();
        $inactive = User::factory()->inactive()->create();

        $this->assertTrue($admin->can('manageMail'));
        $this->assertFalse($staff->can('manageMail'));
        $this->assertFalse($inactive->can('manageMail'));
        $this->assertTrue((new MailboxPolicy)->manage($admin));
        $this->assertFalse((new MailboxPolicy)->manage($staff));
    }

    public function test_jirau_policy_separates_manage_view_and_write(): void
    {
        $admin = User::factory()->create();
        $staff = User::factory()->staff()->create();
        $member = User::factory()->withoutAdminAccess()->create();
        $stranger = User::factory()->withoutAdminAccess()->create();
        $jirau = Jirau::factory()->create(['created_by' => $admin->id]);
        $idle = Jirau::factory()->create([
            'created_by' => $admin->id,
            'is_active' => false,
        ]);
        $jirau->members()->attach($member->id, ['invited_by' => $admin->id]);

        $this->assertTrue($admin->can('manage', Jirau::class));
        $this->assertTrue($admin->can('create', Jirau::class));
        $this->assertTrue($admin->can('view', $jirau));
        $this->assertTrue($admin->can('write', $jirau));
        $this->assertFalse($admin->can('write', $idle));

        $this->assertTrue($staff->can('viewAny', Jirau::class));
        $this->assertTrue($staff->can('view', $jirau));
        $this->assertTrue($staff->can('write', $jirau));
        $this->assertFalse($staff->can('manage', Jirau::class));

        $this->assertTrue($member->can('viewAny', Jirau::class));
        $this->assertTrue($member->can('view', $jirau));
        $this->assertTrue($member->can('write', $jirau));
        $this->assertFalse($member->can('write', $idle));
        $this->assertFalse($member->can('manage', Jirau::class));
        $this->assertFalse($member->can('view', $idle));

        $this->assertFalse($stranger->can('viewAny', Jirau::class));
        $this->assertFalse($stranger->can('view', $jirau));
        $this->assertFalse($stranger->can('write', $jirau));

        $policy = app(JirauPolicy::class);
        $this->assertTrue($policy->trackTime($staff, $jirau));
        $this->assertFalse($policy->trackTime($member, $jirau));
    }

    public function test_user_policy_stays_with_admin(): void
    {
        $admin = User::factory()->create();
        $staff = User::factory()->staff()->create();
        $target = User::factory()->withoutAdminAccess()->create();

        $this->assertTrue($admin->can('create', User::class));
        $this->assertTrue($admin->can('update', $target));
        $this->assertFalse($staff->can('create', User::class));
        $this->assertFalse($staff->can('update', $target));
        $this->assertTrue((new UserPolicy)->viewAny($admin));
        $this->assertFalse((new UserPolicy)->viewAny($staff));
    }
}

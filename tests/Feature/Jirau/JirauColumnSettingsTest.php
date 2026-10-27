<?php

namespace Tests\Feature\Jirau;

use App\Enums\JirauCardType;
use App\Enums\JirauOverviewLane;
use App\Models\Jirau;
use App\Models\JirauCard;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class JirauColumnSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_rename_recolor_and_set_default_due_on_column(): void
    {
        $admin = User::factory()->create();
        $jirau = Jirau::factory()->create(['created_by' => $admin->id]);
        $doing = $jirau->columns()->where('slug', 'doing')->first();
        $staff = User::factory()->staff()->create(['name' => 'Lia Tester']);

        $this->actingAs($admin)
            ->put(route('jirau.columns.update', [$jirau, $doing]), [
                'name' => 'Em execução',
                'color' => '#7c3aed',
                'default_due_hours' => 48,
                'sync_watchers' => '1',
                'user_ids' => [$staff->id],
            ])
            ->assertRedirect(route('jirau.show', $jirau));

        $doing->refresh();
        $this->assertSame('Em execução', $doing->name);
        $this->assertSame('#7c3aed', $doing->color);
        $this->assertSame(48, $doing->default_due_hours);
        $this->assertTrue($doing->watchers()->whereKey($staff->id)->exists());

        $this->actingAs($admin)
            ->get(route('jirau.show', $jirau))
            ->assertOk()
            ->assertSee('Em execução', false)
            ->assertSee('2 dias', false)
            ->assertSee('data-jirau-rename="column-', false)
            ->assertSee('jirau-column-'.$doing->id.'-dialog', false)
            ->assertSee('Lia Tester', false)
            ->assertDontSee('Avisar', false);
    }

    public function test_admin_can_add_column_before_done(): void
    {
        $admin = User::factory()->create();
        $jirau = Jirau::factory()->create(['created_by' => $admin->id]);
        $done = $jirau->doneColumn();
        $before = $jirau->columns()->orderBy('sort_order')->pluck('id')->all();

        $this->actingAs($admin)
            ->from(route('jirau.show', $jirau))
            ->post(route('jirau.columns.store', $jirau), [
                'name' => 'Homologação extra',
                'color' => '#c4a035',
                'default_due_hours' => 24,
            ])
            ->assertRedirect(route('jirau.show', $jirau));

        $column = $jirau->columns()->where('name', 'Homologação extra')->first();
        $this->assertNotNull($column);
        $this->assertSame('#c4a035', $column->color);
        $this->assertSame(24, $column->default_due_hours);
        $this->assertFalse($column->is_inbox);
        $this->assertFalse($column->is_done);
        $this->assertSame(JirauOverviewLane::Backlog, $column->overviewLane());
        $this->assertLessThan((int) $done->fresh()->sort_order, (int) $column->sort_order);

        $this->actingAs($admin)
            ->get(route('jirau.show', $jirau))
            ->assertOk()
            ->assertSee('Homologação extra', false)
            ->assertSee('data-jirau-toggle="new-column"', false)
            ->assertSee('jirau-column--add', false);

        $this->assertNotEquals($before, $jirau->columns()->orderBy('sort_order')->pluck('id')->all());
    }

    public function test_member_cannot_add_column(): void
    {
        $admin = User::factory()->create();
        $jirau = Jirau::factory()->create(['created_by' => $admin->id]);
        $member = User::factory()->withoutAdminAccess()->create();
        $jirau->members()->attach($member->id);

        $this->actingAs($member)
            ->post(route('jirau.columns.store', $jirau), [
                'name' => 'Não deveria',
            ])
            ->assertForbidden();

        $this->assertFalse($jirau->columns()->where('name', 'Não deveria')->exists());
    }

    public function test_admin_can_reorder_columns(): void
    {
        $admin = User::factory()->create();
        $jirau = Jirau::factory()->create(['created_by' => $admin->id]);
        $ids = $jirau->columns()->orderBy('sort_order')->pluck('id')->all();
        $reversed = array_reverse($ids);

        $this->actingAs($admin)
            ->postJson(route('jirau.columns.reorder', $jirau), [
                'ordered_ids' => $reversed,
            ])
            ->assertOk()
            ->assertJsonPath('ok', true);

        $this->assertSame($reversed, $jirau->columns()->orderBy('sort_order')->pluck('id')->all());
    }

    public function test_member_cannot_edit_or_reorder_columns(): void
    {
        $admin = User::factory()->create();
        $jirau = Jirau::factory()->create(['created_by' => $admin->id]);
        $member = User::factory()->withoutAdminAccess()->create();
        $jirau->members()->attach($member->id);
        $doing = $jirau->columns()->where('slug', 'doing')->first();

        $this->actingAs($member)
            ->put(route('jirau.columns.update', [$jirau, $doing]), [
                'name' => 'Hack',
            ])
            ->assertForbidden();

        $this->actingAs($member)
            ->postJson(route('jirau.columns.reorder', $jirau), [
                'ordered_ids' => [$doing->id],
            ])
            ->assertForbidden();
    }

    public function test_card_without_due_date_receives_column_default_when_it_arrives(): void
    {
        $this->travelTo(now()->startOfHour());

        $admin = User::factory()->create();
        $jirau = Jirau::factory()->create(['created_by' => $admin->id]);
        $inbox = $jirau->columns()->where('slug', 'inbox')->first();
        $doing = $jirau->columns()->where('slug', 'doing')->first();
        $doing->update(['default_due_hours' => 48]);

        $card = JirauCard::factory()->demand()->create([
            'jirau_id' => $jirau->id,
            'column_id' => $inbox->id,
            'title' => 'Sem prazo',
            'due_at' => null,
        ]);

        $this->actingAs($admin)
            ->postJson(route('jirau.cards.move', [$jirau, $card]), [
                'column_id' => $doing->id,
                'ordered_ids' => [$card->id],
            ])
            ->assertOk()
            ->assertJsonPath('due_at', now()->addHours(48)->format('d/m'));

        $this->assertEquals(now()->addHours(48)->timestamp, $card->fresh()->due_at?->timestamp);
    }

    public function test_existing_due_date_is_kept_when_entering_a_column(): void
    {
        $admin = User::factory()->create();
        $jirau = Jirau::factory()->create(['created_by' => $admin->id]);
        $inbox = $jirau->columns()->where('slug', 'inbox')->first();
        $doing = $jirau->columns()->where('slug', 'doing')->first();
        $doing->update(['default_due_hours' => 24]);
        $due = now()->addDays(10)->startOfHour();

        $card = JirauCard::factory()->demand()->create([
            'jirau_id' => $jirau->id,
            'column_id' => $inbox->id,
            'title' => 'Já tem prazo',
            'due_at' => $due,
        ]);

        $this->actingAs($admin)
            ->postJson(route('jirau.cards.move', [$jirau, $card]), [
                'column_id' => $doing->id,
                'ordered_ids' => [$card->id],
            ])
            ->assertOk();

        $this->assertEquals($due->timestamp, $card->fresh()->due_at?->timestamp);
    }

    public function test_new_card_in_column_gets_default_due_when_none_is_set(): void
    {
        $this->travelTo(now()->startOfHour());

        $admin = User::factory()->create();
        $jirau = Jirau::factory()->create(['created_by' => $admin->id]);
        $doing = $jirau->columns()->where('slug', 'doing')->first();
        $doing->update(['default_due_hours' => 8]);

        $this->actingAs($admin)
            ->post(route('jirau.cards.store', $jirau), [
                'title' => 'Tarefa da faixa',
                'type' => JirauCardType::Task->value,
                'priority' => 'medium',
                'column_id' => $doing->id,
            ])
            ->assertRedirect();

        $card = JirauCard::query()->where('title', 'Tarefa da faixa')->first();
        $this->assertNotNull($card);
        $this->assertEquals(now()->addHours(8)->timestamp, $card->due_at?->timestamp);
    }

    public function test_admin_can_map_a_custom_column_to_an_overview_lane(): void
    {
        $admin = User::factory()->create();
        $jirau = Jirau::factory()->create(['created_by' => $admin->id]);
        $doing = $jirau->columns()->where('slug', 'doing')->first();

        $this->actingAs($admin)
            ->post(route('jirau.columns.store', $jirau), [
                'name' => 'Homologação extra',
                'color' => '#c4a035',
                'overview_lane' => JirauOverviewLane::Testing->value,
            ])
            ->assertRedirect();

        $column = $jirau->columns()->where('name', 'Homologação extra')->first();
        $this->assertNotNull($column);
        $this->assertSame(JirauOverviewLane::Testing, $column->overviewLane());

        $this->actingAs($admin)
            ->put(route('jirau.columns.update', [$jirau, $column]), [
                'name' => 'Homologação extra',
                'overview_lane' => JirauOverviewLane::Doing->value,
            ])
            ->assertRedirect();

        $this->assertSame(JirauOverviewLane::Doing, $column->fresh()->overviewLane());

        $this->actingAs($admin)
            ->put(route('jirau.columns.update', [$jirau, $doing]), [
                'name' => $doing->name,
                'overview_lane' => JirauOverviewLane::Testing->value,
            ])
            ->assertRedirect();

        $this->assertSame(JirauOverviewLane::Testing, $doing->fresh()->overviewLane());

        $inbox = $jirau->inboxColumn();
        $this->actingAs($admin)
            ->put(route('jirau.columns.update', [$jirau, $inbox]), [
                'name' => $inbox->name,
                'overview_lane' => JirauOverviewLane::Doing->value,
            ])
            ->assertRedirect();

        $this->assertSame(JirauOverviewLane::Demand, $inbox->fresh()->overviewLane());
    }
}

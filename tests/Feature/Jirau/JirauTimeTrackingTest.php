<?php

namespace Tests\Feature\Jirau;

use App\Enums\JirauStaffRole;
use App\Models\Jirau;
use App\Models\JirauCard;
use App\Models\JirauTimeEntry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class JirauTimeTrackingTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_admin_and_staff_see_fazer_on_cards_and_members_do_not(): void
    {
        $admin = User::factory()->create();
        $staff = User::factory()->staff(JirauStaffRole::Developer)->create();
        $jirau = Jirau::factory()->create(['created_by' => $admin->id]);
        $member = User::factory()->withoutAdminAccess()->create();
        $jirau->members()->attach($member->id);
        $card = JirauCard::factory()->create([
            'jirau_id' => $jirau->id,
            'column_id' => $jirau->columns()->where('slug', 'doing')->value('id'),
            'title' => 'API de boletos',
        ]);

        $this->actingAs($admin)
            ->get(route('jirau.show', $jirau))
            ->assertOk()
            ->assertSee('Fazer', false)
            ->assertSee(route('jirau.cards.time.toggle', [$jirau, $card]), false);

        $this->actingAs($staff)
            ->get(route('jirau.show', $jirau))
            ->assertOk()
            ->assertSee('Fazer', false)
            ->assertDontSee('Histórico de horas', false);

        $this->actingAs($member)
            ->get(route('jirau.show', $jirau))
            ->assertOk()
            ->assertDontSee('>Fazer</span>', false)
            ->assertDontSee(route('jirau.cards.time.toggle', [$jirau, $card]), false)
            ->assertDontSee('Histórico de horas', false);
    }

    public function test_staff_can_start_and_pause_time_on_a_card(): void
    {
        Carbon::setTestNow('2026-10-27 09:00:00');

        $admin = User::factory()->create();
        $staff = User::factory()->staff()->create(['name' => 'Ana Dev']);
        $jirau = Jirau::factory()->create(['created_by' => $admin->id]);
        $column = $jirau->columns()->where('slug', 'doing')->first();
        $card = JirauCard::factory()->create([
            'jirau_id' => $jirau->id,
            'column_id' => $column->id,
            'title' => 'API de boletos',
        ]);

        $this->actingAs($staff)
            ->from(route('jirau.show', $jirau))
            ->post(route('jirau.cards.time.toggle', [$jirau, $card]))
            ->assertRedirect();

        $entry = JirauTimeEntry::query()->first();
        $this->assertNotNull($entry);
        $this->assertSame($staff->id, $entry->user_id);
        $this->assertSame($card->id, $entry->card_id);
        $this->assertSame($column->id, $entry->column_id);
        $this->assertSame($column->name, $entry->column_name);
        $this->assertNull($entry->ended_at);

        Carbon::setTestNow('2026-10-27 10:30:00');

        $this->actingAs($staff)
            ->from(route('jirau.show', ['jirau' => $jirau, 'cartao' => $card->id]))
            ->post(route('jirau.cards.time.toggle', [$jirau, $card]))
            ->assertRedirect();

        $fresh = $entry->fresh();
        $this->assertNotNull($fresh->ended_at);
        $this->assertSame(5400, $fresh->seconds);
        $this->assertSame('1h 30min', $fresh->elapsedLabel());
    }

    public function test_member_cannot_track_time(): void
    {
        $admin = User::factory()->create();
        $jirau = Jirau::factory()->create(['created_by' => $admin->id]);
        $member = User::factory()->withoutAdminAccess()->create();
        $jirau->members()->attach($member->id);
        $card = JirauCard::factory()->create([
            'jirau_id' => $jirau->id,
            'column_id' => $jirau->inboxColumn()->id,
        ]);

        $this->actingAs($member)
            ->post(route('jirau.cards.time.toggle', [$jirau, $card]))
            ->assertForbidden();

        $this->assertSame(0, JirauTimeEntry::query()->count());
    }

    public function test_admin_sees_history_by_person_and_etapa(): void
    {
        Carbon::setTestNow('2026-10-27 09:00:00');

        $admin = User::factory()->create(['name' => 'Jader Gabriel']);
        $staff = User::factory()->staff()->create(['name' => 'Ana Dev']);
        $jirau = Jirau::factory()->create(['created_by' => $admin->id]);
        $doing = $jirau->columns()->where('slug', 'doing')->first();
        $card = JirauCard::factory()->create([
            'jirau_id' => $jirau->id,
            'column_id' => $doing->id,
            'title' => 'API de boletos',
        ]);

        $this->actingAs($staff)->post(route('jirau.cards.time.toggle', [$jirau, $card]));
        Carbon::setTestNow('2026-10-27 09:45:00');
        $this->actingAs($staff)->post(route('jirau.cards.time.toggle', [$jirau, $card]));

        $this->actingAs($admin)->post(route('jirau.cards.time.toggle', [$jirau, $card]));
        Carbon::setTestNow('2026-10-27 10:15:00');
        $this->actingAs($admin)->post(route('jirau.cards.time.toggle', [$jirau, $card]));

        $html = $this->actingAs($admin)
            ->get(route('jirau.show', ['jirau' => $jirau, 'cartao' => $card->id]))
            ->assertOk()
            ->assertSee('Histórico de horas', false)
            ->assertSee('1h 15min no total', false)
            ->assertSee('Ana Dev', false)
            ->assertSee('Jader Gabriel', false)
            ->assertSee($doing->name, false)
            ->getContent();

        $this->assertStringContainsString('45min', $html);
        $this->assertStringContainsString('30min', $html);

        $this->actingAs($staff)
            ->get(route('jirau.show', ['jirau' => $jirau, 'cartao' => $card->id]))
            ->assertOk()
            ->assertDontSee('Histórico de horas', false)
            ->assertDontSee('1h 15min no total', false);
    }

    public function test_moving_a_card_splits_running_time_into_the_new_etapa(): void
    {
        Carbon::setTestNow('2026-10-27 09:00:00');

        $admin = User::factory()->create();
        $jirau = Jirau::factory()->create(['created_by' => $admin->id]);
        $inbox = $jirau->inboxColumn();
        $doing = $jirau->columns()->where('slug', 'doing')->first();
        $card = JirauCard::factory()->create([
            'jirau_id' => $jirau->id,
            'column_id' => $inbox->id,
            'title' => 'Pedido',
        ]);

        $this->actingAs($admin)->post(route('jirau.cards.time.toggle', [$jirau, $card]));

        Carbon::setTestNow('2026-10-27 09:10:00');

        $this->actingAs($admin)
            ->postJson(route('jirau.cards.move', [$jirau, $card]), [
                'column_id' => $doing->id,
                'ordered_ids' => [$card->id],
            ])
            ->assertOk();

        Carbon::setTestNow('2026-10-27 09:30:00');
        $this->actingAs($admin)->post(route('jirau.cards.time.toggle', [$jirau, $card]));

        $entries = JirauTimeEntry::query()->orderBy('id')->get();
        $this->assertCount(2, $entries);
        $this->assertSame($inbox->id, $entries[0]->column_id);
        $this->assertSame(600, $entries[0]->seconds);
        $this->assertNotNull($entries[0]->ended_at);
        $this->assertSame($doing->id, $entries[1]->column_id);
        $this->assertSame(1200, $entries[1]->seconds);

        $this->actingAs($admin)
            ->get(route('jirau.show', ['jirau' => $jirau, 'cartao' => $card->id]))
            ->assertOk()
            ->assertSee($inbox->name, false)
            ->assertSee($doing->name, false)
            ->assertSee('30min no total', false);
    }

    public function test_starting_on_another_card_pauses_the_previous(): void
    {
        Carbon::setTestNow('2026-10-27 09:00:00');

        $admin = User::factory()->create();
        $jirau = Jirau::factory()->create(['created_by' => $admin->id]);
        $column = $jirau->columns()->where('slug', 'doing')->first();
        $first = JirauCard::factory()->create([
            'jirau_id' => $jirau->id,
            'column_id' => $column->id,
            'title' => 'Primeiro',
        ]);
        $second = JirauCard::factory()->create([
            'jirau_id' => $jirau->id,
            'column_id' => $column->id,
            'title' => 'Segundo',
        ]);

        $this->actingAs($admin)->post(route('jirau.cards.time.toggle', [$jirau, $first]));
        Carbon::setTestNow('2026-10-27 09:20:00');
        $this->actingAs($admin)->post(route('jirau.cards.time.toggle', [$jirau, $second]));

        $this->assertNotNull($first->timeEntries()->first()->ended_at);
        $this->assertSame(1200, $first->timeEntries()->first()->seconds);
        $this->assertNull($second->timeEntries()->first()->ended_at);
    }

    public function test_inactive_board_cannot_track_time(): void
    {
        $admin = User::factory()->create();
        $jirau = Jirau::factory()->create([
            'created_by' => $admin->id,
            'is_active' => false,
        ]);
        $card = JirauCard::factory()->create([
            'jirau_id' => $jirau->id,
            'column_id' => $jirau->inboxColumn()->id,
        ]);

        $this->actingAs($admin)
            ->get(route('jirau.show', $jirau))
            ->assertOk()
            ->assertDontSee(route('jirau.cards.time.toggle', [$jirau, $card]), false);

        $this->actingAs($admin)
            ->post(route('jirau.cards.time.toggle', [$jirau, $card]))
            ->assertForbidden();
    }
}

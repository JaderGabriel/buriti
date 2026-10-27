<?php

namespace Tests\Unit;

use App\Enums\OpportunityStage;
use App\Enums\TaskStatus;
use App\Models\Contact;
use App\Models\ContactMessage;
use App\Models\Opportunity;
use App\Models\Task;
use App\Services\DashboardKpiService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardKpiServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_unread_kpi_falls_when_inbox_is_cleared(): void
    {
        $this->travelTo(now()->startOfDay()->setTime(12, 0));

        ContactMessage::factory()->count(3)->create([
            'created_at' => now()->subDays(10),
            'updated_at' => now()->subDays(10),
            'read_at' => now()->subDays(2),
        ]);
        ContactMessage::factory()->create([
            'created_at' => now()->subDays(10),
            'updated_at' => now()->subDays(10),
            'read_at' => null,
        ]);

        $unread = $this->card('unread');

        $this->assertSame(1, $unread['value']);
        $this->assertSame(-3, $unread['delta']);
        $this->assertSame('down', $unread['direction']);
        $this->assertSame('up-bad', $unread['polarity']);
        $this->assertStringContainsString('−3 em 7 dias', $unread['delta_label']);
        $this->assertStringStartsWith('M', $unread['path']);
        $this->assertStringContainsString('Z', $unread['area']);
    }

    public function test_contacts_kpi_rises_with_recent_creates(): void
    {
        $this->travelTo(now()->startOfDay()->setTime(12, 0));

        Contact::factory()->count(2)->create([
            'created_at' => now()->subDays(10),
            'updated_at' => now()->subDays(10),
        ]);
        Contact::factory()->count(4)->create([
            'created_at' => now()->subDays(2),
            'updated_at' => now()->subDays(2),
        ]);

        $contacts = $this->card('contacts');

        $this->assertSame(6, $contacts['value']);
        $this->assertSame(4, $contacts['delta']);
        $this->assertSame('up', $contacts['direction']);
        $this->assertSame('up-good', $contacts['polarity']);
        $this->assertSame('+4 em 7 dias', $contacts['delta_label']);
    }

    public function test_open_opportunities_and_tasks_can_trend_down(): void
    {
        $this->travelTo(now()->startOfDay()->setTime(12, 0));

        $stale = Opportunity::factory()->create([
            'created_at' => now()->subDays(12),
            'updated_at' => now()->subDays(12),
            'stage' => OpportunityStage::Qualified,
        ]);
        $stale->update([
            'stage' => OpportunityStage::Won,
            'updated_at' => now()->subDays(1),
        ]);

        Task::factory()->create([
            'created_at' => now()->subDays(11),
            'updated_at' => now()->subDays(11),
            'status' => TaskStatus::Todo,
        ]);
        Task::factory()->create([
            'created_at' => now()->subDays(11),
            'updated_at' => now()->subDays(3),
            'status' => TaskStatus::Done,
        ]);

        $opps = $this->card('opportunities');
        $tasks = $this->card('tasks');

        $this->assertSame(0, $opps['value']);
        $this->assertSame('down', $opps['direction']);
        $this->assertSame(1, $tasks['value']);
        $this->assertSame(-1, $tasks['delta']);
        $this->assertSame('down', $tasks['direction']);
    }

    /** @return array<string, mixed> */
    private function card(string $key): array
    {
        $card = collect(app(DashboardKpiService::class)->cards())->firstWhere('key', $key);
        $this->assertIsArray($card);

        return $card;
    }
}

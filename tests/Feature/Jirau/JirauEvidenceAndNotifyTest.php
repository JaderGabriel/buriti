<?php

namespace Tests\Feature\Jirau;

use App\Enums\JirauCardPriority;
use App\Enums\JirauCardType;
use App\Models\Jirau;
use App\Models\JirauCard;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class JirauEvidenceAndNotifyTest extends TestCase
{
    use RefreshDatabase;

    public function test_member_can_attach_drive_link_and_photo_on_demand(): void
    {
        $this->fakeTelegramApi();
        Storage::fake('local');

        [$jirau, $member] = $this->boardWithMember();

        $this->actingAs($member)
            ->post(route('jirau.demands.store', $jirau), [
                'title' => 'Print do erro no boleto',
                'priority' => JirauCardPriority::High->value,
                'drive_url' => "https://drive.google.com/file/d/abc123/view\nhttps://photos.app.goo.gl/album9",
                'photos' => [UploadedFile::fake()->image('print.jpg')],
            ])
            ->assertRedirect();

        $card = JirauCard::query()->first();
        $this->assertNotNull($card);
        $this->assertSame(1, $card->photoAttachments()->count());
        $this->assertSame(2, $card->links()->count());
        $this->assertTrue($card->links()->where('kind', 'drive')->exists());
        $this->assertTrue($card->links()->where('kind', 'photos')->exists());

        $photo = $card->photoAttachments()->first();
        $this->assertSame('local', $photo->disk);
        Storage::disk('local')->assertExists($photo->path);

        $this->actingAs($member)
            ->get(route('jirau.cards.photos.show', [$jirau, $card, $photo]))
            ->assertOk()
            ->assertHeader('content-type', $photo->mime_type);
    }

    public function test_demand_rejects_unsafe_files_and_urls(): void
    {
        Storage::fake('local');
        [$jirau, $member] = $this->boardWithMember();

        foreach ([
            UploadedFile::fake()->create('doc.pdf', 20, 'application/pdf'),
            UploadedFile::fake()->create('shell.php', 12, 'text/x-php'),
            UploadedFile::fake()->create('pack.zip', 30, 'application/zip'),
            UploadedFile::fake()->create('icon.svg', 8, 'image/svg+xml'),
        ] as $file) {
            $this->actingAs($member)
                ->from(route('jirau.show', $jirau))
                ->post(route('jirau.demands.store', $jirau), [
                    'title' => 'Anexo inválido',
                    'photos' => [$file],
                ])
                ->assertRedirect(route('jirau.show', $jirau))
                ->assertSessionHasErrors('photos.0');
        }

        foreach ([
            'http://drive.google.com/file/d/abc/view',
            'https://evil.example/malware.exe',
            'javascript:alert(1)',
            'https://drive.google.com.evil.test/file',
        ] as $url) {
            $this->actingAs($member)
                ->from(route('jirau.show', $jirau))
                ->post(route('jirau.demands.store', $jirau), [
                    'title' => 'Link inválido',
                    'drive_url' => $url,
                ])
                ->assertRedirect(route('jirau.show', $jirau))
                ->assertSessionHasErrors('drive_url');
        }

        $this->assertSame(0, JirauCard::query()->count());
    }

    public function test_cataloged_move_notifies_team_and_column_watchers_with_priority(): void
    {
        $this->fakeTelegramApi();

        $admin = User::factory()->telegramLinked('555001')->create();
        $staff = User::factory()->staff()->telegramLinked('888002')->create(['name' => 'Rafa Dev']);
        $jirau = Jirau::factory()->create(['created_by' => $admin->id]);
        $inbox = $jirau->columns()->where('slug', 'inbox')->first();
        $backlog = $jirau->columns()->where('slug', 'backlog')->first();
        $backlog->watchers()->sync([$staff->id]);

        $card = JirauCard::factory()->demand()->create([
            'jirau_id' => $jirau->id,
            'column_id' => $inbox->id,
            'title' => 'Ajuste no boleto',
        ]);

        $this->actingAs($admin)
            ->postJson(route('jirau.cards.move', [$jirau, $card]), [
                'column_id' => $backlog->id,
                'ordered_ids' => [$card->id],
            ])
            ->assertOk();

        Http::assertSent(function ($request) {
            $text = (string) ($request->data()['text'] ?? '');

            return (string) ($request->data()['chat_id'] ?? '') === '888002'
                && str_contains($text, '⚡ Prioritário')
                && str_contains($text, 'Demanda catalogada no Jirau')
                && str_contains($text, 'Ajuste no boleto');
        });

        Http::assertSent(function ($request) {
            $text = (string) ($request->data()['text'] ?? '');

            return (string) ($request->data()['chat_id'] ?? '') === '555001'
                && ! str_contains($text, '⚡ Prioritário')
                && str_contains($text, 'Demanda catalogada no Jirau');
        });
    }

    public function test_due_date_change_notifies_and_reminder_command_sends_once(): void
    {
        $this->fakeTelegramApi();

        $admin = User::factory()->telegramLinked('555001')->create();
        $jirau = Jirau::factory()->create(['created_by' => $admin->id]);
        $doing = $jirau->columns()->where('slug', 'doing')->first();
        $card = JirauCard::factory()->create([
            'jirau_id' => $jirau->id,
            'column_id' => $doing->id,
            'title' => 'Entregar API',
            'type' => JirauCardType::Task,
        ]);

        $this->actingAs($admin)
            ->put(route('jirau.cards.update', [$jirau, $card]), [
                'title' => 'Entregar API',
                'type' => JirauCardType::Task->value,
                'priority' => JirauCardPriority::Medium->value,
                'due_at' => now()->addDay()->format('Y-m-d\\TH:i'),
            ])
            ->assertRedirect();

        Http::assertSent(fn ($request) => str_contains((string) ($request->data()['text'] ?? ''), 'Prazo:'));

        $card->forceFill([
            'due_at' => now()->addMinutes(8),
            'telegram_reminder_sent_at' => null,
        ])->save();

        $this->artisan('jirau:telegram-reminders')->assertSuccessful();
        $this->assertNotNull($card->fresh()->telegram_reminder_sent_at);

        Http::assertSent(fn ($request) => str_contains((string) ($request->data()['text'] ?? ''), 'Prazo do Jirau em breve'));

        Http::fake([
            'api.telegram.org/*' => Http::response(['ok' => true, 'result' => true]),
        ]);
        $this->artisan('jirau:telegram-reminders')->assertSuccessful();
        Http::assertNothingSent();
    }

    public function test_admin_can_assign_column_watchers_and_member_cannot(): void
    {
        [$jirau, $member] = $this->boardWithMember();
        $admin = User::query()->where('is_admin', true)->first();
        $staff = User::factory()->staff()->create(['name' => 'Lia Tester']);
        $doing = $jirau->columns()->where('slug', 'doing')->first();

        $this->actingAs($member)
            ->put(route('jirau.columns.watchers', [$jirau, $doing]), [
                'user_ids' => [$staff->id],
            ])
            ->assertForbidden();

        $this->actingAs($admin)
            ->put(route('jirau.columns.watchers', [$jirau, $doing]), [
                'user_ids' => [$staff->id],
            ])
            ->assertRedirect(route('jirau.show', $jirau));

        $this->assertTrue($doing->fresh()->watchers()->whereKey($staff->id)->exists());

        $this->actingAs($admin)
            ->get(route('jirau.show', $jirau))
            ->assertOk()
            ->assertSee('Avisar', false)
            ->assertSee('Lia Tester', false);
    }

    public function test_demand_form_exposes_safe_evidence_fields(): void
    {
        [$jirau, $member] = $this->boardWithMember();

        $this->actingAs($member)
            ->get(route('jirau.show', $jirau))
            ->assertOk()
            ->assertSee('Fotos e prints', false)
            ->assertSee('Google Drive', false)
            ->assertDontSee('Avisar', false);
    }

    /** @return array{0: Jirau, 1: User} */
    private function boardWithMember(): array
    {
        $admin = User::factory()->telegramLinked('555001')->create();
        $jirau = Jirau::factory()->create(['created_by' => $admin->id]);
        $member = User::factory()->withoutAdminAccess()->create();
        $jirau->members()->attach($member->id);

        return [$jirau, $member];
    }
}

<?php

namespace Tests\Feature\Admin;

use App\Models\Attachment;
use App\Models\Contact;
use App\Models\Opportunity;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AttachmentTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create();
        Storage::fake('public');
        Storage::fake('local');
    }

    public function test_admin_can_attach_document_to_contact(): void
    {
        $contact = Contact::factory()->create();
        $file = UploadedFile::fake()->create('proposta.pdf', 120, 'application/pdf');

        $this->actingAs($this->admin)
            ->post(route('admin.attachments.store', ['type' => 'contacts', 'id' => $contact->id]), [
                'file' => $file,
                'kind' => 'document',
                'title' => 'Proposta comercial',
            ])
            ->assertRedirect();

        $attachment = Attachment::query()->first();
        $this->assertNotNull($attachment);
        $this->assertSame('Proposta comercial', $attachment->title);
        $this->assertSame('local', $attachment->disk);
        $this->assertTrue($attachment->attachable->is($contact));
        Storage::disk('local')->assertExists($attachment->path);
    }

    public function test_admin_can_attach_document_to_opportunity_and_task(): void
    {
        $opportunity = Opportunity::factory()->create();
        $task = Task::factory()->create();

        $this->actingAs($this->admin)
            ->post(route('admin.attachments.store', ['type' => 'opportunities', 'id' => $opportunity->id]), [
                'file' => UploadedFile::fake()->create('brief.pdf', 80, 'application/pdf'),
                'kind' => 'document',
            ])
            ->assertRedirect();

        $this->actingAs($this->admin)
            ->post(route('admin.attachments.store', ['type' => 'tasks', 'id' => $task->id]), [
                'file' => UploadedFile::fake()->create('checklist.pdf', 40, 'application/pdf'),
                'kind' => 'document',
            ])
            ->assertRedirect();

        $this->assertSame(1, $opportunity->attachments()->count());
        $this->assertSame(1, $task->attachments()->count());
    }

    public function test_user_only_accepts_photos(): void
    {
        $user = User::factory()->create();

        $this->actingAs($this->admin)
            ->post(route('admin.attachments.store', ['type' => 'users', 'id' => $user->id]), [
                'file' => UploadedFile::fake()->create('cv.pdf', 50, 'application/pdf'),
                'kind' => 'document',
            ])
            ->assertStatus(422);

        $this->actingAs($this->admin)
            ->post(route('admin.attachments.store', ['type' => 'users', 'id' => $user->id]), [
                'file' => UploadedFile::fake()->image('retrato.jpg'),
                'kind' => 'photo',
            ])
            ->assertRedirect();

        $this->assertSame(1, $user->attachments()->count());
    }

    public function test_project_accepts_documents_and_media_without_changing_home_fields(): void
    {
        $project = Project::factory()->create([
            'is_public' => true,
            'name' => 'Portfólio Visível',
            'logo_path' => null,
        ]);

        $this->actingAs($this->admin)
            ->post(route('admin.attachments.store', ['type' => 'projects', 'id' => $project->id]), [
                'file' => UploadedFile::fake()->create('contrato.pdf', 100, 'application/pdf'),
                'kind' => 'document',
            ])
            ->assertRedirect();

        $this->actingAs($this->admin)
            ->post(route('admin.attachments.store', ['type' => 'projects', 'id' => $project->id]), [
                'file' => UploadedFile::fake()->image('mockup.png'),
                'kind' => 'media',
            ])
            ->assertRedirect();

        $project->refresh();
        $this->assertSame(2, $project->attachments()->count());
        $this->assertNull($project->logo_path);

        $this->get(route('home'))
            ->assertOk()
            ->assertSee('Portfólio Visível', false)
            ->assertDontSee('contrato.pdf', false);
    }

    public function test_admin_can_download_soft_delete_restore_and_purge_attachment(): void
    {
        $contact = Contact::factory()->create();

        $this->actingAs($this->admin)
            ->post(route('admin.attachments.store', ['type' => 'contacts', 'id' => $contact->id]), [
                'file' => UploadedFile::fake()->create('nota.pdf', 30, 'application/pdf'),
                'kind' => 'document',
            ]);

        $attachment = Attachment::query()->firstOrFail();
        $path = $attachment->path;

        $this->actingAs($this->admin)
            ->get(route('admin.attachments.download', $attachment))
            ->assertOk();

        $preview = $this->actingAs($this->admin)
            ->get(route('admin.attachments.preview', $attachment))
            ->assertOk();

        $this->assertStringContainsString(
            'inline',
            strtolower((string) $preview->headers->get('content-disposition'))
        );

        $docx = UploadedFile::fake()->create('plano.docx', 20, 'application/vnd.openxmlformats-officedocument.wordprocessingml.document');

        $this->actingAs($this->admin)
            ->post(route('admin.attachments.store', ['type' => 'contacts', 'id' => $contact->id]), [
                'file' => $docx,
                'kind' => 'document',
            ]);

        $office = Attachment::query()->where('original_name', 'plano.docx')->firstOrFail();

        $this->actingAs($this->admin)
            ->get(route('admin.attachments.preview', $office))
            ->assertStatus(415);

        $this->actingAs($this->admin)
            ->delete(route('admin.attachments.destroy', $attachment))
            ->assertRedirect();

        $this->assertSoftDeleted('attachments', ['id' => $attachment->id]);
        Storage::disk('local')->assertExists($path);
        $this->assertDatabaseHas('audit_logs', ['action' => 'attachment.trashed']);

        $this->actingAs($this->admin)
            ->get(route('admin.attachments.download', $attachment))
            ->assertNotFound();

        $this->actingAs($this->admin)
            ->post(route('admin.attachments.restore', $attachment))
            ->assertRedirect();

        $this->assertNotSoftDeleted('attachments', ['id' => $attachment->id]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'attachment.restored']);

        $this->actingAs($this->admin)
            ->delete(route('admin.attachments.destroy', $attachment->fresh()))
            ->assertRedirect();

        $this->actingAs($this->admin)
            ->delete(route('admin.attachments.purge', $attachment->fresh()))
            ->assertRedirect();

        $this->assertDatabaseMissing('attachments', ['id' => $attachment->id]);
        Storage::disk('local')->assertMissing($path);
        $this->assertDatabaseHas('audit_logs', ['action' => 'attachment.purged']);
    }

    public function test_attachment_create_writes_audit_log(): void
    {
        $contact = Contact::factory()->create();

        $this->actingAs($this->admin)
            ->post(route('admin.attachments.store', ['type' => 'contacts', 'id' => $contact->id]), [
                'file' => UploadedFile::fake()->create('brief.pdf', 20, 'application/pdf'),
                'kind' => 'document',
                'title' => 'Brief',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'attachment.created',
            'user_id' => $this->admin->id,
        ]);
    }
}

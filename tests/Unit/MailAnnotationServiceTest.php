<?php

namespace Tests\Unit;

use App\Enums\MailMarker;
use App\Models\MailLabel;
use App\Models\Project;
use App\Services\MailAnnotationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MailAnnotationServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_remember_and_overlay_keep_labels_markers_and_links(): void
    {
        $service = app(MailAnnotationService::class);
        $project = Project::factory()->create(['name' => 'CRM Buriti']);
        $label = MailLabel::query()->where('slug', 'proposta')->first();
        $this->assertNotNull($label);

        $record = $service->remember('contato@exemplo.com', [
            'folder' => 'INBOX',
            'uid' => 9,
            'message_id' => '<abc@buriti>',
            'subject' => 'Orçamento',
            'from' => 'ana@empresa.com',
        ]);
        $service->setMarker($record, MailMarker::Importante);
        $service->attachLabel($record, $label);
        $service->addLink($record, 'project', $project->id);

        $decorated = $service->decorate('contato@exemplo.com', 'INBOX', [[
            'uid' => 9,
            'message_id' => '<abc@buriti>',
            'flagged' => true,
            'subject' => 'Orçamento',
        ]]);

        $this->assertTrue($decorated[0]['starred']);
        $this->assertSame('importante', $decorated[0]['marker']);
        $this->assertSame('Proposta', $decorated[0]['labels'][0]['name']);
        $this->assertSame('Projeto', $decorated[0]['links'][0]['kind_label']);
        $this->assertSame('CRM Buriti', $decorated[0]['links'][0]['name']);
    }
}

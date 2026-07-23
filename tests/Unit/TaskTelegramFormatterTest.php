<?php

namespace Tests\Unit;

use App\Enums\CrmActivityType;
use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Models\Contact;
use App\Models\CrmActivity;
use App\Models\Task;
use App\Services\Telegram\TaskTelegramFormatter;
use Illuminate\Database\Eloquent\Collection;
use Tests\TestCase;

class TaskTelegramFormatterTest extends TestCase
{
    public function test_done_task_card_shows_success_mark_and_activities(): void
    {
        $contact = new Contact(['name' => 'Ana Cliente']);
        $contact->id = 5;

        $activity = new CrmActivity([
            'type' => CrmActivityType::Call,
            'subject' => 'Ligação de fecho',
            'body' => 'Cliente aprovou escopo.',
            'happened_at' => now()->subHour(),
        ]);
        $activity->setRelation('contact', $contact);

        $task = new Task([
            'title' => 'Reunião fechada',
            'description' => null,
            'status' => TaskStatus::Done,
            'priority' => TaskPriority::High,
            'due_at' => now()->subHour(),
        ]);
        $task->id = 42;
        $task->setRelation('contact', $contact);
        $task->setRelation('project', null);
        $task->setRelation('activities', new Collection([$activity]));

        $html = app(TaskTelegramFormatter::class)->card($task);

        $this->assertStringContainsString('✅', $html);
        $this->assertStringContainsString('Compromisso concluído', $html);
        $this->assertStringContainsString('Êxito', $html);
        $this->assertStringContainsString('Atividades do contato', $html);
        $this->assertStringContainsString('Cliente aprovou escopo.', $html);
        $this->assertStringContainsString('Ligação de fecho', $html);
        $this->assertStringContainsString('Reunião fechada', $html);
    }

    public function test_list_line_marks_done_and_preview_activity(): void
    {
        $activity = new CrmActivity([
            'type' => CrmActivityType::Note,
            'subject' => 'Follow',
            'body' => 'Ligar amanhã de manhã',
        ]);

        $task = new Task([
            'title' => 'Follow-up',
            'description' => null,
            'status' => TaskStatus::Done,
            'priority' => TaskPriority::Medium,
            'due_at' => now(),
        ]);
        $task->id = 7;
        $task->setRelation('activities', new Collection([$activity]));

        $line = app(TaskTelegramFormatter::class)->listLine($task);

        $this->assertStringContainsString('✅', $line);
        $this->assertStringContainsString('Concluída', $line);
        $this->assertStringContainsString('Nota', $line);
        $this->assertStringContainsString('Ligar amanhã', $line);
    }

    public function test_open_task_card_still_shows_activities_section(): void
    {
        $task = new Task([
            'title' => 'Sem atividades',
            'description' => null,
            'status' => TaskStatus::Todo,
            'priority' => TaskPriority::Medium,
        ]);
        $task->id = 3;
        $task->setRelation('contact', null);
        $task->setRelation('project', null);
        $task->setRelation('activities', new Collection);

        $html = app(TaskTelegramFormatter::class)->card($task);

        $this->assertStringContainsString('Atividades do contato', $html);
        $this->assertStringContainsString('Sem atividades', $html);
        $this->assertStringContainsString('Compromisso na agenda', $html);
    }
}

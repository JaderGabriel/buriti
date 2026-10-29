<?php

namespace App\Services;

use App\Enums\MailMarker;
use App\Models\IdeaNote;
use App\Models\Jirau;
use App\Models\MailLabel;
use App\Models\MailLink;
use App\Models\MailRecord;
use App\Models\Project;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use InvalidArgumentException;

class MailAnnotationService
{
    public const MAX_ATTACHMENT_BYTES = 10 * 1024 * 1024;

    /**
     * @return array{
     *     labels: Collection<int, MailLabel>,
     *     markers: array<string, string>,
     *     projects: Collection<int, Project>,
     *     jiraus: Collection<int, Jirau>,
     *     ideas: Collection<int, IdeaNote>
     * }
     */
    public function catalog(): array
    {
        return [
            'labels' => MailLabel::query()->orderBy('sort_order')->orderBy('name')->get(),
            'markers' => MailMarker::options(),
            'projects' => Project::query()->orderBy('name')->get(['id', 'name']),
            'jiraus' => Jirau::query()->with('project:id,name')->orderBy('name')->get(['id', 'name', 'project_id']),
            'ideas' => IdeaNote::query()->latest('id')->limit(80)->get(['id', 'title', 'body']),
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $messages
     * @return list<array<string, mixed>>
     */
    public function decorate(string $account, string $folder, array $messages): array
    {
        if ($messages === []) {
            return [];
        }

        $records = $this->recordsFor($account, $folder, $messages);

        return array_map(function (array $message) use ($records, $folder): array {
            $record = $this->matchRecord($records, $folder, $message);

            return $message + $this->overlay($record, (bool) ($message['flagged'] ?? false));
        }, $messages);
    }

    /**
     * @param  array<string, mixed>  $selected
     * @return array<string, mixed>
     */
    public function hydrate(string $account, array $selected): array
    {
        $folder = (string) ($selected['folder'] ?? 'INBOX');
        $records = $this->recordsFor($account, $folder, [$selected]);
        $record = $this->matchRecord($records, $folder, $selected);

        return $selected + $this->overlay($record, (bool) ($selected['flagged'] ?? false));
    }

    /**
     * @param  array<string, mixed>  $message
     */
    public function remember(string $account, array $message): MailRecord
    {
        $folder = (string) ($message['folder'] ?? 'INBOX');
        $uid = (int) ($message['uid'] ?? 0);
        $mid = MailRecord::normalizeId($message['message_id'] ?? null);
        $key = MailRecord::key($mid, $folder, $uid);

        $record = null;
        if ($mid !== null) {
            $record = MailRecord::query()
                ->where('account', $account)
                ->where('rfc_message_id', $mid)
                ->first();
        }
        if ($record === null) {
            $record = MailRecord::query()
                ->where('account', $account)
                ->where('folder', $folder)
                ->where('uid', $uid)
                ->first();
        }
        if ($record === null) {
            $record = MailRecord::query()->firstOrNew([
                'account' => $account,
                'message_key' => $key,
            ]);
        }

        $record->fill([
            'account' => $account,
            'message_key' => $key,
            'rfc_message_id' => $mid ?? $record->rfc_message_id,
            'folder' => $folder,
            'uid' => $uid,
            'subject' => mb_substr((string) ($message['subject'] ?? $record->subject ?? ''), 0, 180) ?: null,
            'from_address' => mb_substr((string) ($message['from'] ?? $record->from_address ?? ''), 0, 180) ?: null,
        ]);
        $record->save();

        return $record;
    }

    public function setMarker(MailRecord $record, ?MailMarker $marker): void
    {
        $record->marker = $marker;
        $record->save();
    }

    public function attachLabel(MailRecord $record, MailLabel $label): void
    {
        $record->labels()->syncWithoutDetaching([$label->id]);
    }

    public function detachLabel(MailRecord $record, MailLabel $label): void
    {
        $record->labels()->detach($label->id);
    }

    public function createLabel(string $name, string $color = 'slate'): MailLabel
    {
        $name = trim($name);
        $slug = Str::slug($name) ?: 'etiqueta';
        $base = $slug;
        $i = 2;
        while (MailLabel::query()->where('slug', $slug)->exists()) {
            $slug = $base.'-'.$i;
            $i++;
        }

        $color = in_array($color, MailLabel::COLORS, true) ? $color : 'slate';

        return MailLabel::query()->create([
            'name' => mb_substr($name, 0, 40),
            'slug' => $slug,
            'color' => $color,
            'sort_order' => (int) MailLabel::query()->max('sort_order') + 1,
        ]);
    }

    public function addLink(MailRecord $record, string $kind, int $id): MailLink
    {
        $class = $this->linkableClass($kind);
        $target = $class::query()->find($id);
        if (! $target instanceof Model) {
            throw new InvalidArgumentException('Registo para vincular não encontrado.');
        }

        return $record->links()->firstOrCreate([
            'linkable_type' => $class,
            'linkable_id' => $target->getKey(),
        ]);
    }

    public function removeLink(MailRecord $record, int $linkId): void
    {
        $record->links()->whereKey($linkId)->delete();
    }

    /**
     * @param  list<array<string, mixed>>  $messages
     * @return Collection<int, MailRecord>
     */
    private function recordsFor(string $account, string $folder, array $messages): Collection
    {
        $uids = array_values(array_unique(array_map(fn (array $message) => (int) ($message['uid'] ?? 0), $messages)));
        $mids = array_values(array_filter(array_map(
            fn (array $message) => MailRecord::normalizeId($message['message_id'] ?? null),
            $messages,
        )));

        return MailRecord::query()
            ->with(['labels', 'links.linkable'])
            ->where('account', $account)
            ->where(function ($query) use ($folder, $uids, $mids) {
                $query->where(function ($inner) use ($folder, $uids) {
                    $inner->where('folder', $folder)->whereIn('uid', $uids);
                });
                if ($mids !== []) {
                    $query->orWhereIn('rfc_message_id', $mids);
                }
            })
            ->get();
    }

    /**
     * @param  Collection<int, MailRecord>  $records
     * @param  array<string, mixed>  $message
     */
    private function matchRecord(Collection $records, string $folder, array $message): ?MailRecord
    {
        $uid = (int) ($message['uid'] ?? 0);
        $mid = MailRecord::normalizeId($message['message_id'] ?? null);

        if ($mid !== null) {
            $byId = $records->first(fn (MailRecord $record) => $record->rfc_message_id === $mid);
            if ($byId) {
                return $byId;
            }
        }

        return $records->first(
            fn (MailRecord $record) => $record->folder === $folder && (int) $record->uid === $uid
        );
    }

    /**
     * @return array{starred: bool, marker: ?string, marker_label: ?string, labels: list<array{id: int, name: string, color: string}>, links: list<array{id: int, kind: string, kind_label: string, name: string, href: ?string}>}
     */
    private function overlay(?MailRecord $record, bool $flagged): array
    {
        $labels = [];
        $links = [];
        if ($record) {
            foreach ($record->labels as $label) {
                $labels[] = [
                    'id' => $label->id,
                    'name' => $label->name,
                    'color' => $label->color,
                ];
            }
            foreach ($record->links as $link) {
                $links[] = [
                    'id' => $link->id,
                    'kind' => $link->kind(),
                    'kind_label' => $link->kindLabel(),
                    'name' => $link->displayName(),
                    'href' => $this->linkHref($link),
                ];
            }
        }

        return [
            'starred' => $flagged,
            'marker' => $record?->marker?->value,
            'marker_label' => $record?->marker?->label(),
            'labels' => $labels,
            'links' => $links,
            'record_id' => $record?->id,
        ];
    }

    private function linkHref(MailLink $link): ?string
    {
        return match ($link->kind()) {
            'project' => route('admin.projects.edit', $link->linkable_id),
            'jirau' => route('jirau.show', $link->linkable_id),
            default => null,
        };
    }

    /**
     * @return class-string<Model>
     */
    public function linkableClass(string $kind): string
    {
        return match ($kind) {
            'project' => Project::class,
            'jirau' => Jirau::class,
            'idea' => IdeaNote::class,
            default => throw new InvalidArgumentException('Tipo de vínculo inválido.'),
        };
    }
}

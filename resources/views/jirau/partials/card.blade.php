@php
    $progress = $card->checklistProgress();
    $canDrag = $canDrag ?? false;
    $cardHref = route('jirau.show', ['jirau' => $jirau, 'cartao' => $card->id]);
@endphp
<article
    class="jirau-card jirau-card--{{ $card->type->value }} jirau-card--p-{{ $card->priority->value }}"
    data-jirau-card
    data-card-id="{{ $card->id }}"
    data-column-id="{{ $card->column_id }}"
    data-href="{{ $cardHref }}"
    @if($canDrag) data-jirau-draggable @endif
>
    @if($canDrag)
        <div class="jirau-card__hit">
    @else
        <a href="{{ $cardHref }}" class="jirau-card__hit">
    @endif
        <div class="jirau-card__labels">
            @foreach($card->labels as $label)
                <span class="jirau-label" style="--jirau-label: {{ $label->color }}">{{ $label->name }}</span>
            @endforeach
        </div>
        <p class="jirau-card__key">{{ $card->key() }} · {{ $card->type->label() }}</p>
        <h3 class="jirau-card__title">{{ $card->title }}</h3>
        @if(! empty($showBoard) && $card->jirau)
            <p class="jirau-card__board">{{ $card->jirau->project?->name ?? $card->jirau->name }}</p>
        @endif
        <div class="jirau-card__meta">
            @if($card->project_step_id)
                <span class="jirau-chip" title="Etapa do projeto">Etapa</span>
            @endif
            @if($card->estimate)
                <span class="jirau-chip" title="Estimativa">{{ $card->estimate }} pts</span>
            @elseif($card->type->value === 'demand' && $card->column?->is_inbox)
                <span class="jirau-chip jirau-chip--warn">Aguardando triagem</span>
            @endif
            @if($card->due_at)
                <span class="jirau-chip {{ $card->due_at->isPast() ? 'jirau-chip--warn' : '' }}">{{ $card->due_at->format('d/m') }}</span>
            @endif
            @if($progress['total'] > 0)
                <span class="jirau-chip">{{ $progress['done'] }}/{{ $progress['total'] }}</span>
            @endif
            @if(($card->comments_count ?? 0) > 0)
                <span class="jirau-chip">{{ $card->comments_count }}</span>
            @endif
            @if(($card->attachments_count ?? 0) > 0)
                <span class="jirau-chip" title="Fotos e prints">{{ $card->attachments_count }} foto{{ (int) $card->attachments_count === 1 ? '' : 's' }}</span>
            @endif
            @if(($card->links_count ?? 0) > 0)
                <span class="jirau-chip" title="Google Drive ou álbum">Drive</span>
            @endif
            @if($card->assignee)
                <span class="jirau-avatar" title="{{ $card->assignee->name }} · {{ $card->assignee->jirauTeamLabel() }}">{{ $card->assignee->initials() }}</span>
            @endif
        </div>
    @if($canDrag)
        </div>
    @else
        </a>
    @endif
</article>

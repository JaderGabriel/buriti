@php
    /** @var array{lane: string, title: string, hint: string, tone: string, items: \Illuminate\Support\Collection} $column */
@endphp
<section class="home-board__column home-board__column--{{ $column['tone'] }}" data-home-lane="{{ $column['lane'] }}">
    <header class="home-board__header">
        <div>
            <h2>
                @if($column['lane'] === 'featured')
                    <x-ui.icon name="star" class="h-4 w-4" />
                @endif
                {{ $column['title'] }}
            </h2>
            <p>{{ $column['hint'] }}</p>
        </div>
        <span class="pm-board__count" data-column-count>{{ $column['items']->count() }}</span>
    </header>
    <div class="home-board__list" data-column-list>
        @forelse($column['items'] as $project)
            @include('admin.home-projects.partials.card', ['project' => $project, 'lane' => $column['lane']])
        @empty
            <p class="pm-board__empty" data-empty>Solte projetos aqui.</p>
        @endforelse
    </div>
</section>

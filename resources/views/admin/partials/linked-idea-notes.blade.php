@php
    /** @var \Illuminate\Support\Collection<int, \App\Models\IdeaNote> $notes */
    $notes = $notes ?? collect();
    $companyId = $companyId ?? null;
    $contactId = $contactId ?? null;
    $tilts = ['-rotate-1', 'rotate-1', 'rotate-0', '-rotate-2', 'rotate-2'];
@endphp

<article class="linked-ideas">
    <div class="mb-4 flex flex-wrap items-center justify-between gap-2">
        <div>
            <h2 class="crm-detail__section-title">Ideias / post-its</h2>
            <p class="mt-1 text-xs text-mist">Anotações do mural alocadas a este registro.</p>
        </div>
        <div class="flex flex-wrap items-center gap-3">
            <form method="POST" action="{{ route('admin.idea-notes.store') }}">
                @csrf
                <input type="hidden" name="color" value="amber">
                @if($companyId)
                    <input type="hidden" name="company_id" value="{{ $companyId }}">
                @endif
                @if($contactId)
                    <input type="hidden" name="contact_id" value="{{ $contactId }}">
                @endif
                <button type="submit" class="text-sm font-semibold text-brand-bright hover:underline">
                    + Novo post-it
                </button>
            </form>
            <a href="{{ route('admin.dashboard') }}#ideias" class="text-sm text-brand-bright hover:underline">Ver mural</a>
        </div>
    </div>

    @if($notes->isNotEmpty())
        <div class="postit-board linked-ideas__board grid gap-4 sm:grid-cols-2">
            @foreach($notes as $index => $note)
                @php
                    $color = $note->color?->value ?? 'amber';
                    $tilt = $tilts[$index % count($tilts)];
                @endphp
                <a
                    href="{{ route('admin.dashboard') }}#ideia-{{ $note->id }}"
                    class="idea-postit idea-postit--preview postit postit-{{ $color }} relative flex min-h-[11rem] min-w-0 max-w-full flex-col overflow-hidden p-4 pt-7 shadow-md transition duration-200 hover:-translate-y-0.5 hover:rotate-0 hover:shadow-lg {{ $tilt }}"
                    title="Abrir no mural"
                >
                    <span class="postit-pin" aria-hidden="true"></span>
                    <p class="idea-postit__preview-title">{{ $note->displayTitle() }}</p>
                    @if(filled($note->body))
                        <p class="idea-postit__preview-body">{{ \Illuminate\Support\Str::limit($note->body, 180) }}</p>
                    @endif
                    <div class="idea-postit__preview-foot mt-auto">
                        @if($note->company && ! isset($hideCompany))
                            <span>{{ $note->company->displayName() }}</span>
                        @endif
                        @if($note->contact && ! isset($hideContact))
                            <span>{{ $note->contact->name }}</span>
                        @endif
                        <span class="idea-postit__preview-open">Abrir no mural</span>
                    </div>
                </a>
            @endforeach
        </div>
    @else
        <p class="text-sm text-mist">Nenhum post-it alocado ainda. Crie um aqui ou vincule no mural.</p>
    @endif
</article>

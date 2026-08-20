@php
    /** @var \Illuminate\Support\Collection<int, \App\Models\IdeaNote> $notes */
    $notes = $notes ?? collect();
@endphp

@if($notes->isNotEmpty())
    <article class="rounded-sm border border-line bg-panel p-5">
        <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
            <div>
                <h2 class="crm-detail__section-title">Ideias / post-its</h2>
                <p class="mt-1 text-xs text-mist">Anotações do mural vinculadas a este registro.</p>
            </div>
            <a href="{{ route('admin.dashboard') }}#ideias" class="text-sm text-brand-bright hover:underline">Ver mural</a>
        </div>
        <ul class="space-y-3">
            @foreach($notes as $note)
                <li class="rounded-sm border border-line/70 px-3 py-3">
                    <div class="flex flex-wrap items-start justify-between gap-2">
                        <div class="min-w-0">
                            <p class="font-medium text-snow">{{ $note->displayTitle() }}</p>
                            @if(filled($note->body))
                                <p class="mt-1 text-sm text-mist whitespace-pre-wrap">{{ \Illuminate\Support\Str::limit($note->body, 220) }}</p>
                            @endif
                            <p class="mt-2 text-xs text-mist">
                                @if($note->company && ! isset($hideCompany))
                                    <a href="{{ route('admin.companies.show', $note->company) }}" class="text-brand-bright hover:underline">{{ $note->company->displayName() }}</a>
                                    @if($note->contact) · @endif
                                @endif
                                @if($note->contact && ! isset($hideContact))
                                    <a href="{{ route('admin.contacts.show', $note->contact) }}" class="text-brand-bright hover:underline">{{ $note->contact->name }}</a>
                                @endif
                            </p>
                        </div>
                        <a href="{{ route('admin.dashboard') }}#ideia-{{ $note->id }}" class="shrink-0 text-xs text-brand-bright hover:underline">Abrir no mural</a>
                    </div>
                </li>
            @endforeach
        </ul>
    </article>
@endif

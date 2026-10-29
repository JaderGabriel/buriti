@extends('layouts.admin')

@section('content')
<div class="crm-workspace">
    <div class="crm-workspace__header">
        <div>
            <p class="crm-workspace__eyebrow">Entrega · Qualidade</p>
            <h1 class="crm-workspace__title">Depoimentos</h1>
            <p class="crm-workspace__lead">O cliente escreve e sela o original. O admin medeia o texto publicado e pode vincular o projeto. Aprovados com selo íntegro entram na faixa da home e no modal do projeto.</p>
        </div>
        <div class="crm-workspace__actions">
            <a href="{{ route('testimonials.seal') }}" class="pm-btn pm-btn--ghost" target="_blank" rel="noopener">
                <x-ui.icon name="qualified" class="h-4 w-4" />
                Verificar selo
            </a>
            <a href="{{ route('admin.testimonials.create') }}" class="pm-btn pm-btn--primary">
                <x-ui.icon name="quote" class="h-4 w-4" />
                Convidar cliente
            </a>
        </div>
    </div>

    @include('admin.testimonials.partials.sample-tools')

    <div class="crm-status-strip mb-5">
        @foreach(\App\Enums\TestimonialStatus::cases() as $status)
            <a
                href="{{ route('admin.testimonials.index', ['status' => $status->value, 'q' => request('q')]) }}"
                @class(['crm-status-strip__item', 'crm-status-strip__item--'.$status->tone(), 'is-active' => request('status') === $status->value])
            >
                <span class="crm-status-strip__icon"><x-ui.icon :name="$status->icon()" class="h-4 w-4" /></span>
                <span>
                    <strong>{{ $status->label() }}</strong>
                    <small>{{ $status->description() }}</small>
                </span>
                <em>{{ (int) ($statusCounts[$status->value] ?? 0) }}</em>
            </a>
        @endforeach
    </div>

    <form method="GET" class="mb-6 flex flex-wrap gap-3">
        <input type="search" name="q" value="{{ request('q') }}" placeholder="Buscar cliente, texto ou código BURI-…" class="min-w-[14rem] flex-1 rounded-sm border border-line bg-ink/40 px-3 py-2 text-sm text-snow">
        <select name="status" class="rounded-sm border border-line bg-ink/40 px-3 py-2 text-sm text-snow">
            <option value="">Todos os status</option>
            @foreach($statuses as $value => $label)
                <option value="{{ $value }}" @selected(request('status') === $value)>{{ $label }}</option>
            @endforeach
        </select>
        <button type="submit" class="rounded-sm border border-line px-4 py-2 text-sm hover:border-brand-bright/50">Filtrar</button>
    </form>

    @if($testimonials->isEmpty())
        <div class="rounded-sm border border-dashed border-line px-6 py-16 text-center text-mist">
            Ainda não há depoimentos. Convide um contato do CRM — o link não entra no menu do site.
        </div>
    @else
        <ul class="grid gap-3">
            @foreach($testimonials as $item)
                <li>
                    <a href="{{ route('admin.testimonials.show', $item) }}" class="testimonial-row">
                        <span class="testimonial-row__stars" aria-label="{{ $item->stars() ?? 0 }} estrelas">
                            @for($i = 1; $i <= 5; $i++)
                                <span @class(['is-on' => $i <= (int) $item->stars()])>★</span>
                            @endfor
                        </span>
                        <span class="testimonial-row__copy">
                            <strong>{{ $item->displayName() }}</strong>
                            <small>
                                {{ $item->contact?->name }}
                                @if($item->project)
                                    · {{ $item->project->name }}
                                @endif
                                @if($item->verification_code)
                                    · {{ $item->verification_code }}
                                @endif
                            </small>
                        </span>
                        @if($item->is_sample)
                            <span class="testimonial-sample-chip">Amostra</span>
                        @endif
                        <x-admin.crm-badge :status="$item->status" compact />
                    </a>
                </li>
            @endforeach
        </ul>
        <div class="mt-6">{{ $testimonials->links() }}</div>
    @endif
</div>
@endsection

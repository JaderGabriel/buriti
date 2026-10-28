@extends('layouts.admin')

@section('content')
@php
    $open = $testimonial->isOpenInvite();
@endphp
<div class="crm-workspace testimonial-dossier">
    <a href="{{ route('admin.testimonials.index') }}" class="contact-dossier__back">
        <x-ui.icon name="journey" class="h-4 w-4" />
        Depoimentos
    </a>

    <header class="crm-workspace__header">
        <div>
            <p class="crm-workspace__eyebrow">Selo {{ $testimonial->verification_code ?: 'pendente' }}</p>
            <h1 class="crm-workspace__title">{{ $testimonial->displayName() }}</h1>
            <p class="crm-workspace__lead">
                {{ $testimonial->contact?->name }}
                @if($testimonial->project)
                    · {{ $testimonial->project->name }}
                @endif
            </p>
        </div>
        <x-admin.crm-badge :status="$testimonial->status" />
    </header>

    @if($inviteUrl)
        <div class="testimonial-invite" role="status">
            <p>Envie este link ao cliente. Não entra no site público e o token não fica guardado em claro.</p>
            <input type="text" readonly value="{{ $inviteUrl }}" class="w-full rounded-sm border border-line bg-ink/40 px-3 py-2 font-mono text-xs text-snow">
        </div>
    @elseif($open)
        <form method="POST" action="{{ route('admin.testimonials.reinvite', $testimonial) }}" class="mb-5">
            @csrf
            <button type="submit" class="pm-btn pm-btn--ghost">Gerar novo link (invalida o anterior)</button>
        </form>
    @endif

    <div class="testimonial-grid">
        <section class="qa-panel">
            <header class="qa-panel__head">
                <div>
                    <p class="qa-panel__eyebrow">Original do cliente</p>
                    <h2 class="qa-panel__title">Imutável após o envio</h2>
                </div>
            </header>
            <div class="qa-panel__body p-4">
                @if($testimonial->original_body)
                    <p class="testimonial-stars" aria-label="{{ $testimonial->original_stars }} estrelas">
                        @for($i = 1; $i <= 5; $i++)
                            <span @class(['is-on' => $i <= (int) $testimonial->original_stars])>★</span>
                        @endfor
                    </p>
                    <p class="text-sm text-mist">Como assinou: {{ $testimonial->original_display_name }}</p>
                    <blockquote class="testimonial-quote">{{ $testimonial->original_body }}</blockquote>
                    <p class="mt-3 font-mono text-[11px] text-mist">origem {{ $testimonial->sourceFingerprint() ?: '—' }}</p>
                @else
                    <p class="text-sm text-mist">Ainda não enviado. O hash de origem nasce quando o cliente submete o texto.</p>
                @endif
            </div>
        </section>

        <section class="qa-panel">
            <header class="qa-panel__head">
                <div>
                    <p class="qa-panel__eyebrow">Resultado mediado</p>
                    <h2 class="qa-panel__title">O que poderá ser publicado mais tarde</h2>
                </div>
            </header>
            <div class="qa-panel__body p-4">
                @if($testimonial->submitted_at)
                    <form method="POST" action="{{ route('admin.testimonials.moderate', $testimonial) }}" class="space-y-3">
                        @csrf
                        <label class="flex items-center gap-2 text-sm">
                            <input type="checkbox" name="use_original" value="1" @checked(! $testimonial->wasMediated())>
                            Aprovar o texto original sem alterações
                        </label>
                        <label class="block text-sm">
                            <span class="mb-1 block text-mist">Estrelas publicadas</span>
                            <select name="published_stars" class="rounded-sm border border-line bg-ink/40 px-3 py-2 text-snow">
                                @for($i = 1; $i <= 5; $i++)
                                    <option value="{{ $i }}" @selected((int) old('published_stars', $testimonial->published_stars ?? $testimonial->original_stars) === $i)>{{ $i }}</option>
                                @endfor
                            </select>
                        </label>
                        <label class="block text-sm">
                            <span class="mb-1 block text-mist">Nome visível</span>
                            <input type="text" name="published_display_name" value="{{ old('published_display_name', $testimonial->published_display_name ?? $testimonial->original_display_name) }}" maxlength="120" class="w-full rounded-sm border border-line bg-ink/40 px-3 py-2 text-snow">
                        </label>
                        <label class="block text-sm">
                            <span class="mb-1 block text-mist">Texto publicado</span>
                            <textarea name="published_body" rows="6" maxlength="4000" class="w-full rounded-sm border border-line bg-ink/40 px-3 py-2 text-sm text-snow">{{ old('published_body', $testimonial->published_body ?? $testimonial->original_body) }}</textarea>
                        </label>
                        <label class="block text-sm">
                            <span class="mb-1 block text-mist">Notas internas</span>
                            <input type="text" name="moderation_notes" value="{{ old('moderation_notes', $testimonial->moderation_notes) }}" maxlength="500" class="w-full rounded-sm border border-line bg-ink/40 px-3 py-2 text-snow">
                        </label>
                        <div class="flex flex-wrap gap-2">
                            <button type="submit" name="decision" value="approved" class="pm-btn pm-btn--primary">Aprovar</button>
                            <button type="submit" name="decision" value="rejected" class="pm-btn pm-btn--ghost">Recusar</button>
                            <button type="submit" name="decision" value="archived" class="pm-btn pm-btn--ghost">Arquivar</button>
                        </div>
                    </form>
                    @if($testimonial->wasMediated())
                        <p class="mt-3 text-xs text-amber-200">O texto publicado difere do original. O selo de origem continua a provar o que o cliente escreveu.</p>
                    @endif
                    <p class="mt-3 font-mono text-[11px] text-mist">resultado {{ $testimonial->resultFingerprint() ?: '—' }}</p>
                @else
                    <p class="text-sm text-mist">A mediação abre depois do envio do cliente.</p>
                @endif
            </div>
        </section>
    </div>

    <div class="testimonial-grid mt-4">
        <section class="qa-panel">
            <header class="qa-panel__head">
                <div>
                    <p class="qa-panel__eyebrow">Projeto</p>
                    <h2 class="qa-panel__title">Vínculo posterior</h2>
                </div>
            </header>
            <form method="POST" action="{{ route('admin.testimonials.project', $testimonial) }}" class="space-y-3 p-4">
                @csrf
                @method('PUT')
                <select name="project_id" class="w-full rounded-sm border border-line bg-ink/40 px-3 py-2 text-sm text-snow">
                    <option value="">Sem projeto</option>
                    @foreach($projects as $project)
                        <option value="{{ $project->id }}" @selected($testimonial->project_id === $project->id)>{{ $project->name }}</option>
                    @endforeach
                </select>
                <button type="submit" class="pm-btn pm-btn--ghost">Guardar vínculo</button>
            </form>
        </section>

        <section class="qa-panel">
            <header class="qa-panel__head">
                <div>
                    <p class="qa-panel__eyebrow">Autenticidade</p>
                    <h2 class="qa-panel__title">{{ $authenticity['ok'] ? 'Selo íntegro' : 'Ainda sem selo completo' }}</h2>
                </div>
            </header>
            <dl class="testimonial-seal p-4">
                <div>
                    <dt>Código</dt>
                    <dd>{{ $testimonial->verification_code ?: '—' }}</dd>
                </div>
                <div>
                    <dt>Origem SHA-256</dt>
                    <dd class="font-mono text-[11px] break-all">{{ $authenticity['source_digest'] ?: '—' }}</dd>
                </div>
                <div>
                    <dt>Resultado SHA-256</dt>
                    <dd class="font-mono text-[11px] break-all">{{ $authenticity['result_digest'] ?: '—' }}</dd>
                </div>
                <div>
                    <dt>HMAC</dt>
                    <dd class="font-mono text-[11px] break-all">{{ $testimonial->seal ?: '—' }}</dd>
                </div>
                <div>
                    <dt>Checagem</dt>
                    <dd>
                        origem {{ $authenticity['source'] ? 'ok' : '—' }}
                        · resultado {{ $authenticity['result'] ? 'ok' : '—' }}
                        · selo {{ $authenticity['seal'] ? 'ok' : '—' }}
                    </dd>
                </div>
            </dl>
            @if($testimonial->verification_code && $testimonial->status->value === 'approved')
                <p class="px-4 pb-4 text-xs text-mist">
                    Verificação pública (unlisted):
                    <a href="{{ route('testimonials.seal', $testimonial->verification_code) }}" class="text-brand-bright">{{ route('testimonials.seal', $testimonial->verification_code) }}</a>
                </p>
            @endif
        </section>
    </div>
</div>
@endsection

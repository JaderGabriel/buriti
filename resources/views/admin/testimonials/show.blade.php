@extends('layouts.admin')

@section('content')
@php
    $open = $testimonial->isOpenInvite();
    $tone = $testimonial->status->tone();
    $stars = (int) ($testimonial->stars() ?? 0);
@endphp
<div class="contact-dossier testimonial-dossier">
    <a href="{{ route('admin.testimonials.index') }}" class="contact-dossier__back">
        <x-ui.icon name="journey" class="h-4 w-4" />
        Depoimentos
    </a>

    <header class="contact-dossier__hero contact-dossier__hero--{{ $tone }}">
        <div class="contact-dossier__identity">
            <span class="contact-dossier__avatar contact-dossier__avatar--quote" aria-hidden="true">
                <x-ui.icon name="quote" class="h-6 w-6" />
            </span>
            <div class="min-w-0">
                <p class="contact-dossier__eyebrow">Entrega · Depoimentos</p>
                <h1 class="contact-dossier__title">{{ $testimonial->displayName() }}</h1>
                <div class="contact-dossier__tags">
                    <x-admin.crm-badge :status="$testimonial->status" />
                    <span class="contact-dossier__chip">
                        <x-ui.icon name="diagnose" class="h-3.5 w-3.5" />
                        Selo {{ $testimonial->verification_code ?: 'pendente' }}
                    </span>
                    @if($testimonial->contact)
                        <a href="{{ route('admin.contacts.show', $testimonial->contact) }}" class="contact-dossier__chip contact-dossier__chip--link">
                            <x-ui.icon name="contact" class="h-3.5 w-3.5" />
                            {{ $testimonial->contact->name }}
                        </a>
                    @endif
                    @if($testimonial->project)
                        <a href="{{ route('admin.projects.edit', $testimonial->project) }}" class="contact-dossier__chip contact-dossier__chip--link">
                            <x-ui.icon name="project" class="h-3.5 w-3.5" />
                            {{ $testimonial->project->name }}
                        </a>
                    @endif
                </div>
                <ul class="contact-dossier__facts">
                    @if($stars > 0)
                        <li>
                            <x-ui.icon name="star" class="h-3.5 w-3.5" />
                            <span>{{ $stars }} estrela{{ $stars === 1 ? '' : 's' }}</span>
                        </li>
                    @endif
                    @if($testimonial->invitedBy)
                        <li>
                            <x-ui.icon name="mail" class="h-3.5 w-3.5" />
                            <span>Convite de {{ $testimonial->invitedBy->name }}</span>
                        </li>
                    @endif
                    @if($testimonial->submitted_at)
                        <li>
                            <x-ui.icon name="calendar" class="h-3.5 w-3.5" />
                            <span>Enviado {{ $testimonial->submitted_at->timezone(config('app.timezone'))->format('d/m/Y H:i') }}</span>
                        </li>
                    @endif
                </ul>
            </div>
        </div>

        <div class="contact-dossier__quick">
            @if($open)
                <form method="POST" action="{{ route('admin.testimonials.reinvite', $testimonial) }}">
                    @csrf
                    <button type="submit" class="contact-dossier__quick-btn contact-dossier__quick-btn--mail">
                        <x-ui.icon name="mail" class="h-4 w-4" />
                        Novo link
                    </button>
                </form>
            @endif
            @if($testimonial->verification_code && $testimonial->status === \App\Enums\TestimonialStatus::Approved)
                <a href="{{ route('testimonials.seal', $testimonial->verification_code) }}" class="contact-dossier__quick-btn contact-dossier__quick-btn--primary" target="_blank" rel="noopener">
                    <x-ui.icon name="qualified" class="h-4 w-4" />
                    Ver selo
                </a>
            @endif
        </div>
    </header>

    @if($inviteUrl)
        <div class="testimonial-invite" role="status">
            <span class="contact-conduct__badge contact-conduct__badge--quote">
                <x-ui.icon name="mail" class="h-4 w-4" />
            </span>
            <div class="min-w-0 flex-1">
                <p>Copie o link e envie ao cliente pelo canal que preferir. O sistema <strong>não dispara e-mail</strong>. O token não fica salvo em texto puro.</p>
                <div class="testimonial-invite__row">
                    <input id="testimonial-invite-url" type="text" readonly value="{{ $inviteUrl }}" class="w-full rounded-sm border border-line bg-ink/40 px-3 py-2 font-mono text-xs text-snow" onclick="this.select()">
                    <button type="button" class="pm-btn pm-btn--primary" data-copy="#testimonial-invite-url">
                        <x-ui.icon name="copy" class="h-4 w-4" />
                        <span data-copy-label>Copiar</span>
                    </button>
                </div>
            </div>
        </div>
    @endif

    <div class="contact-dossier__stats">
        <div class="contact-dossier__stat">
            <span class="contact-dossier__stat-icon contact-dossier__stat-icon--quote"><x-ui.icon name="star" class="h-5 w-5" /></span>
            <div>
                <strong>{{ $stars ?: '—' }}</strong>
                <small>estrelas do cliente</small>
            </div>
        </div>
        <div class="contact-dossier__stat">
            <span class="contact-dossier__stat-icon contact-dossier__stat-icon--seal"><x-ui.icon name="qualified" class="h-5 w-5" /></span>
            <div>
                <strong>{{ $authenticity['ok'] ? 'Íntegro' : 'Pendente' }}</strong>
                <small>selo de autenticidade</small>
            </div>
        </div>
        <div class="contact-dossier__stat">
            <span class="contact-dossier__stat-icon contact-dossier__stat-icon--activity"><x-ui.icon :name="$testimonial->status->icon()" class="h-5 w-5" /></span>
            <div>
                <strong>{{ $testimonial->status->label() }}</strong>
                <small>{{ $testimonial->status->description() }}</small>
            </div>
        </div>
        <div class="contact-dossier__stat">
            <span class="contact-dossier__stat-icon contact-dossier__stat-icon--project"><x-ui.icon name="project" class="h-5 w-5" /></span>
            <div>
                <strong>{{ $testimonial->project?->name ?: 'Sem projeto' }}</strong>
                <small>vínculo posterior</small>
            </div>
        </div>
    </div>

    <div class="contact-dossier__layout">
        <div class="contact-dossier__main space-y-4">
            <article class="contact-conduct__panel contact-conduct__panel--quote">
                <div class="contact-conduct__panel-head">
                    <span class="contact-conduct__badge contact-conduct__badge--quote">
                        <x-ui.icon name="quote" class="h-4 w-4" />
                    </span>
                    <div>
                        <h3>Original do cliente</h3>
                        <p>Imutável após o envio</p>
                    </div>
                </div>
                @if($testimonial->original_body)
                    <p class="testimonial-stars" aria-label="{{ $testimonial->original_stars }} estrelas">
                        @for($i = 1; $i <= 5; $i++)
                            <span @class(['is-on' => $i <= (int) $testimonial->original_stars])>★</span>
                        @endfor
                    </p>
                    <p class="mt-2 text-sm text-mist">Nome no envio: {{ $testimonial->original_display_name }}</p>
                    <blockquote class="testimonial-quote">{{ $testimonial->original_body }}</blockquote>
                    <p class="mt-3 font-mono text-[11px] text-mist">origem {{ $testimonial->sourceFingerprint() ?: '—' }}</p>
                @else
                    <div class="contact-conduct__empty">
                        <x-ui.icon name="quote" class="h-8 w-8 opacity-40" />
                        <p>Ainda não enviado. O hash de origem é gerado quando o cliente envia o texto.</p>
                    </div>
                @endif
            </article>

            <article class="contact-conduct__panel contact-conduct__panel--mediate">
                <div class="contact-conduct__panel-head">
                    <span class="contact-conduct__badge contact-conduct__badge--mediate">
                        <x-ui.icon name="pencil" class="h-4 w-4" />
                    </span>
                    <div>
                        <h3>Mediação</h3>
                        <p>Versão publicada, sem alterar o original selado</p>
                    </div>
                    <x-admin.crm-badge :status="$testimonial->status" compact class="ml-auto shrink-0" />
                </div>
                @if($testimonial->submitted_at)
                    @php
                        $locked = $testimonial->status === \App\Enums\TestimonialStatus::Archived;
                        $currentDecision = $testimonial->status->value;
                    @endphp
                    <div @class(['testimonial-mediate', 'testimonial-mediate--'.$tone, 'is-locked' => $locked])>
                        @if($locked)
                            <p class="testimonial-mediate__banner">
                                <x-ui.icon name="folder" class="h-4 w-4" />
                                Arquivado. A mediação está encerrada e os botões de decisão ficam inativos.
                            </p>
                            @if($testimonial->published_body)
                                <blockquote class="testimonial-quote">{{ $testimonial->published_body }}</blockquote>
                            @endif
                            @if($testimonial->moderation_notes)
                                <p class="mt-3 text-sm text-mist">Nota: {{ $testimonial->moderation_notes }}</p>
                            @endif
                        @else
                            <form method="POST" action="{{ route('admin.testimonials.moderate', $testimonial) }}" class="space-y-3">
                                @csrf
                                <label class="flex items-center gap-2 text-sm">
                                    <input type="checkbox" name="use_original" value="1" @checked(! $testimonial->wasMediated()) class="accent-[var(--color-brand)]">
                                    Usar o texto original sem alterações
                                </label>
                                <label class="block text-sm">
                                    <span class="mb-1 inline-flex items-center gap-1.5 text-mist">
                                        <x-ui.icon name="star" class="h-3.5 w-3.5" />
                                        Estrelas publicadas
                                    </span>
                                    <select name="published_stars" class="w-full rounded-sm border border-line bg-ink/40 px-3 py-2 text-snow">
                                        @for($i = 1; $i <= 5; $i++)
                                            <option value="{{ $i }}" @selected((int) old('published_stars', $testimonial->published_stars ?? $testimonial->original_stars) === $i)>{{ $i }} ★</option>
                                        @endfor
                                    </select>
                                </label>
                                <label class="block text-sm">
                                    <span class="mb-1 inline-flex items-center gap-1.5 text-mist">
                                        <x-ui.icon name="contact" class="h-3.5 w-3.5" />
                                        Nome visível
                                    </span>
                                    <input type="text" name="published_display_name" value="{{ old('published_display_name', $testimonial->published_display_name ?? $testimonial->original_display_name) }}" maxlength="120" class="w-full rounded-sm border border-line bg-ink/40 px-3 py-2 text-snow">
                                </label>
                                <label class="block text-sm">
                                    <span class="mb-1 inline-flex items-center gap-1.5 text-mist">
                                        <x-ui.icon name="message" class="h-3.5 w-3.5" />
                                        Texto publicado
                                    </span>
                                    <textarea name="published_body" rows="6" maxlength="4000" class="w-full rounded-sm border border-line bg-ink/40 px-3 py-2 text-sm text-snow">{{ old('published_body', $testimonial->published_body ?? $testimonial->original_body) }}</textarea>
                                </label>
                                <label class="block text-sm">
                                    <span class="mb-1 block text-mist">Notas internas</span>
                                    <input type="text" name="moderation_notes" value="{{ old('moderation_notes', $testimonial->moderation_notes) }}" maxlength="500" class="w-full rounded-sm border border-line bg-ink/40 px-3 py-2 text-snow">
                                </label>
                                <div class="testimonial-mediate__actions">
                                    <button
                                        type="submit"
                                        name="decision"
                                        value="approved"
                                        @disabled($currentDecision === 'approved')
                                        @class(['pm-btn', 'pm-btn--primary', 'is-current' => $currentDecision === 'approved'])
                                    >
                                        <x-ui.icon name="qualified" class="h-4 w-4" />
                                        {{ $currentDecision === 'approved' ? 'Aprovado' : 'Aprovar' }}
                                    </button>
                                    <button
                                        type="submit"
                                        name="decision"
                                        value="rejected"
                                        @disabled($currentDecision === 'rejected')
                                        @class(['pm-btn', 'pm-btn--ghost', 'is-current' => $currentDecision === 'rejected'])
                                    >
                                        <x-ui.icon name="lost" class="h-4 w-4" />
                                        {{ $currentDecision === 'rejected' ? 'Recusado' : 'Recusar' }}
                                    </button>
                                    <button
                                        type="submit"
                                        name="decision"
                                        value="archived"
                                        class="pm-btn pm-btn--ghost"
                                    >
                                        <x-ui.icon name="folder" class="h-4 w-4" />
                                        Arquivar
                                    </button>
                                </div>
                            </form>
                        @endif
                        @if($testimonial->wasMediated())
                            <p class="mt-3 text-xs text-amber-200">O texto publicado difere do original. O selo de origem continua comprovando o que o cliente escreveu.</p>
                        @endif
                        <p class="mt-3 font-mono text-[11px] text-mist">resultado {{ $testimonial->resultFingerprint() ?: '—' }}</p>
                    </div>
                @else
                    <div class="contact-conduct__empty">
                        <x-ui.icon name="pencil" class="h-8 w-8 opacity-40" />
                        <p>A mediação abre depois do envio do cliente.</p>
                    </div>
                @endif
            </article>
        </div>

        <aside class="contact-dossier__aside space-y-4">
            <article class="contact-conduct__panel contact-conduct__panel--projects">
                <div class="contact-conduct__panel-head">
                    <span class="contact-conduct__badge contact-conduct__badge--project">
                        <x-ui.icon name="project" class="h-4 w-4" />
                    </span>
                    <div>
                        <h3>Projeto</h3>
                        <p>Vínculo posterior</p>
                    </div>
                </div>
                <form method="POST" action="{{ route('admin.testimonials.project', $testimonial) }}" class="space-y-3">
                    @csrf
                    @method('PUT')
                    <select name="project_id" class="w-full rounded-sm border border-line bg-ink/40 px-3 py-2 text-sm text-snow">
                        <option value="">Sem projeto</option>
                        @foreach($projects as $project)
                            <option value="{{ $project->id }}" @selected($testimonial->project_id === $project->id)>{{ $project->name }}</option>
                        @endforeach
                    </select>
                    <button type="submit" class="pm-btn pm-btn--ghost">
                        <x-ui.icon name="project" class="h-4 w-4" />
                        Salvar vínculo
                    </button>
                </form>
            </article>

            <article class="contact-conduct__panel contact-conduct__panel--seal">
                <div class="contact-conduct__panel-head">
                    <span class="contact-conduct__badge contact-conduct__badge--seal">
                        <x-ui.icon name="diagnose" class="h-4 w-4" />
                    </span>
                    <div>
                        <h3>{{ $authenticity['ok'] ? 'Selo íntegro' : 'Ainda sem selo completo' }}</h3>
                        <p>Autenticidade criptográfica</p>
                    </div>
                </div>
                <dl class="testimonial-seal">
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
                        <dd class="testimonial-seal__checks">
                            <span @class(['testimonial-seal__flag', 'is-ok' => $authenticity['source']])>
                                <x-ui.icon :name="$authenticity['source'] ? 'qualified' : 'lost'" class="h-3.5 w-3.5" />
                                origem
                            </span>
                            <span @class(['testimonial-seal__flag', 'is-ok' => $authenticity['result']])>
                                <x-ui.icon :name="$authenticity['result'] ? 'qualified' : 'lost'" class="h-3.5 w-3.5" />
                                resultado
                            </span>
                            <span @class(['testimonial-seal__flag', 'is-ok' => $authenticity['seal']])>
                                <x-ui.icon :name="$authenticity['seal'] ? 'qualified' : 'lost'" class="h-3.5 w-3.5" />
                                selo
                            </span>
                        </dd>
                    </div>
                </dl>
                @if($testimonial->verification_code && $testimonial->status->value === 'approved')
                    <p class="mt-3 text-xs text-mist">
                        Verificação pública (não listada):
                        <a href="{{ route('testimonials.seal', $testimonial->verification_code) }}" class="text-brand-bright break-all">{{ route('testimonials.seal', $testimonial->verification_code) }}</a>
                    </p>
                @endif
            </article>
        </aside>
    </div>
</div>
@endsection

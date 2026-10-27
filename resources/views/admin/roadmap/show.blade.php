@extends('layouts.admin')

@section('content')
    <div class="admin-hero">
        <div>
            <p class="admin-hero__kicker">Sistema</p>
            <h1 class="admin-hero__title">Roadmap de produto</h1>
            <p class="admin-hero__lead">
                Ideias de módulos, submódulos e recursos inspirados em concorrentes — com impacto medido sobre o BURI-TI, não um backlog prometido.
            </p>
        </div>
        <div class="admin-hero__date" title="Documento vivo em docs/ROADMAP.md">
            <strong>{{ $summary['total'] }} ideias</strong>
            <span>índice médio {{ $summary['avg_indice'] }}</span>
        </div>
    </div>

    <p class="mb-6 max-w-3xl text-sm text-mist">
        O BURI-TI é o sistema operativo de uma consultoria pequena (site + CRM + Jirau), não um HubSpot. Cada ideia abaixo diz <em>o que copiar</em>, <em>em que onda</em> e <em>o que parte no código</em> se for feita.
        Texto longo no repositório em <code class="text-snow">docs/ROADMAP.md</code>
        ·
        <a class="text-brand-bright hover:underline" href="{{ route('admin.integrations.edit') }}">playbook das integrações actuais</a>
        ·
        consolidação do CRM em <code class="text-snow">docs/CRM-CONSOLIDACAO.md</code>.
    </p>

    <section class="roadmap-waves" aria-label="Ondas do roadmap">
        @foreach($waves as $wave)
            <article class="roadmap-wave roadmap-wave--{{ $wave['id'] }}">
                <p class="roadmap-wave__kicker">{{ $wave['label'] }} · {{ $wave['horizon'] }}</p>
                <p class="roadmap-wave__count">{{ $summary['by_wave'][$wave['id']] }}</p>
                <p class="roadmap-wave__goal">{{ $wave['goal'] }}</p>
            </article>
        @endforeach
    </section>

    <section class="roadmap-section" aria-labelledby="roadmap-map-title">
        <h2 id="roadmap-map-title" class="roadmap-section__title">Mapa de módulos</h2>
        <p class="roadmap-section__lead">Hoje a barra tem cinco módulos. O alvo cresce por dentro (submódulos) e só depois abre módulos novos.</p>
        <div class="roadmap-maps">
            <div>
                <p class="roadmap-maps__label">Hoje</p>
                <ol class="roadmap-map">
                    @foreach($currentModules as $mod)
                        <li class="roadmap-map__mod roadmap-map__mod--{{ $mod['id'] }}">
                            <strong>{{ $mod['label'] }}</strong>
                            <span>{{ implode(' · ', $mod['items']) }}</span>
                        </li>
                    @endforeach
                </ol>
            </div>
            <div>
                <p class="roadmap-maps__label">Alvo</p>
                <ol class="roadmap-map">
                    @foreach($proposedModules as $mod)
                        <li class="roadmap-map__mod roadmap-map__mod--{{ $mod['id'] }} roadmap-map__mod--{{ $mod['state'] }}">
                            <strong>{{ $mod['label'] }} @if($mod['state'] === 'new')<em>novo</em>@elseif($mod['state'] === 'grow')<em>cresce</em>@endif</strong>
                            <span>{{ implode(' · ', $mod['items']) }}</span>
                        </li>
                    @endforeach
                </ol>
            </div>
        </div>
    </section>

    <section class="roadmap-section" aria-labelledby="roadmap-loop-title">
        <h2 id="roadmap-loop-title" class="roadmap-section__title">Ciclo que os PSA de agência fecham — e o BURI-TI ainda não</h2>
        <p class="roadmap-section__lead">Accelo/Scoro vendem este fluxo. O CRM actual pára em Projeto + Agenda. As ideias da onda Agora e Próximo preenchem as caixas a tracejado.</p>
        <ol class="roadmap-loop">
            @foreach($deliveryLoop as $i => $step)
                <li class="roadmap-loop__step @if($i >= 2) roadmap-loop__step--future @endif">
                    <span class="roadmap-loop__n">{{ $i + 1 }}</span>
                    <strong>{{ $step['title'] }}</strong>
                    <span>{{ $step['note'] }}</span>
                </li>
            @endforeach
        </ol>
    </section>

    <section class="roadmap-section" aria-labelledby="roadmap-comp-title">
        <h2 id="roadmap-comp-title" class="roadmap-section__title">O que os concorrentes fazem de útil aqui</h2>
        <div class="roadmap-table-wrap">
            <table class="roadmap-table">
                <thead>
                    <tr>
                        <th>Produto</th>
                        <th>Tipo</th>
                        <th>O que vale copiar (e o que não)</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($competitors as $row)
                        <tr>
                            <th>{{ $row['name'] }}</th>
                            <td>{{ $row['kind'] }}</td>
                            <td>{{ $row['take'] }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </section>

    <section class="roadmap-section" data-roadmap aria-labelledby="roadmap-ideas-title">
        <h2 id="roadmap-ideas-title" class="roadmap-section__title">Ideias pontuadas</h2>
        <p class="roadmap-section__lead">
            {{ $scoring['indice'] }}
            Valor: {{ $scoring['valor'] }}
            Esforço: {{ $scoring['esforco'] }}
            Risco: {{ $scoring['risco'] }}
        </p>
        <div class="roadmap-filters" role="toolbar" aria-label="Filtrar ideias">
            @foreach($filters as $filter)
                <button type="button" class="roadmap-filters__btn" data-roadmap-filter="{{ $filter['id'] }}" @if($filter['id'] === 'all') aria-pressed="true" @else aria-pressed="false" @endif>
                    {{ $filter['label'] }}
                </button>
            @endforeach
        </div>
        <p class="roadmap-empty" data-roadmap-empty hidden>Nenhuma ideia neste filtro.</p>
        <div class="roadmap-ideas">
            @foreach($ideas as $idea)
                <article
                    class="roadmap-idea roadmap-idea--{{ $idea['prioridade'] === 'alta' ? 'alta' : ($idea['prioridade'] === 'média' ? 'media' : 'baixa') }}"
                    data-roadmap-item
                    data-wave="{{ $idea['wave'] }}"
                    data-module="{{ $idea['module'] }}"
                    data-kind="{{ $idea['kind'] }}"
                    id="ideia-{{ $idea['id'] }}"
                >
                    <header class="roadmap-idea__head">
                        <div class="roadmap-idea__tags">
                            <span>{{ $idea['kind_label'] }}</span>
                            <span>{{ $idea['module_label'] }}</span>
                            <span>{{ $idea['wave_label'] }}</span>
                        </div>
                        <p class="roadmap-idea__score" title="Índice de impacto">
                            <strong>{{ $idea['indice'] }}</strong>
                            <span>prioridade {{ $idea['prioridade'] }}</span>
                        </p>
                    </header>
                    <h3>{{ $idea['title'] }}</h3>
                    <p class="roadmap-idea__inspired">Inspirado em {{ $idea['inspired'] }}</p>
                    <p>{{ $idea['summary'] }}</p>
                    <dl class="roadmap-meters">
                        <div>
                            <dt>Valor {{ $idea['valor'] }}/5</dt>
                            <dd style="--fill: {{ $idea['valor'] }}"><i></i></dd>
                        </div>
                        <div>
                            <dt>Esforço {{ $idea['esforco_label'] }}</dt>
                            <dd style="--fill: {{ $idea['esforco'] }}"><i></i></dd>
                        </div>
                        <div>
                            <dt>Risco {{ $idea['risco'] }}/5</dt>
                            <dd style="--fill: {{ $idea['risco'] }}"><i></i></dd>
                        </div>
                    </dl>
                    <p class="roadmap-idea__impact"><strong>Impacto no sistema.</strong> {{ $idea['impact'] }}</p>
                    <ul class="roadmap-idea__surfaces">
                        @foreach($idea['surfaces'] as $surface)
                            <li>{{ $surface }}</li>
                        @endforeach
                    </ul>
                </article>
            @endforeach
        </div>
    </section>

    <section class="roadmap-section" aria-labelledby="roadmap-impact-title">
        <h2 id="roadmap-impact-title" class="roadmap-section__title">Como cada onda mexe no sistema</h2>
        <p class="roadmap-section__lead">Não é só “mais um ecrã”: nav, schema, jobs, site público e testes mudam de escala juntos.</p>
        <div class="roadmap-table-wrap">
            <table class="roadmap-table">
                <thead>
                    <tr>
                        <th>Superfície</th>
                        <th>Agora</th>
                        <th>Próximo</th>
                        <th>Escala</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($impacts as $row)
                        <tr>
                            <th>{{ $row['area'] }}</th>
                            <td>{{ $row['agora'] }}</td>
                            <td>{{ $row['proximo'] }}</td>
                            <td>{{ $row['escala'] }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </section>

    <section class="roadmap-section" aria-labelledby="roadmap-out-title">
        <h2 id="roadmap-out-title" class="roadmap-section__title">Fora de escopo (de propósito)</h2>
        <ul class="roadmap-out">
            @foreach($outOfScope as $item)
                <li>{{ $item }}</li>
            @endforeach
        </ul>
    </section>
@endsection

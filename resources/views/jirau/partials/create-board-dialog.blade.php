@php
    $lockProject = (bool) ($lockProject ?? false);
    $selectedProject = $selectedProject ?? null;
    $currentJirau = $currentJirau ?? ($jirau ?? null);
    $createBoardProjects = $lockProject
        ? collect()
        : ($createBoardProjects ?? \App\Models\Project::query()
            ->forJirauPicker()
            ->withCount('jiraus')
            ->get(['id', 'name', 'status', 'company_id']));
    $createBoardCompanies = $createBoardCompanies ?? \App\Models\Company::query()
        ->orderByRaw("CASE status WHEN 'active' THEN 0 ELSE 1 END")
        ->orderBy('trade_name')
        ->orderBy('name')
        ->get(['id', 'name', 'trade_name', 'status']);
    $selectedProjectId = (int) old('project_id', $selectedProjectId ?? $selectedProject?->id ?? $currentJirau?->project_id ?? 0);
    $selectedCompanyId = (int) old('company_id', $selectedCompanyId ?? $selectedProject?->company_id ?? $currentJirau?->company_id ?? 0);
    $createBoardOpen = old('_board_create') === '1';
    $submitLabel = $lockProject && $selectedProject && $selectedProject->jiraus->isNotEmpty()
        ? 'Criar outro quadro'
        : 'Criar quadro';
@endphp
<div
    id="jirau-create-board-dialog"
    class="jirau-dialog"
    data-jirau-panel="new-board"
    role="dialog"
    aria-modal="true"
    aria-labelledby="jirau-create-board-title"
    aria-hidden="{{ $createBoardOpen ? 'false' : 'true' }}"
    @unless($createBoardOpen) hidden @endunless
>
    <button type="button" class="jirau-dialog__backdrop" data-jirau-dialog-close aria-label="Fechar"></button>
    <div class="jirau-dialog__panel jirau-dialog__panel--board">
        <div class="jirau-dialog__head">
            <div>
                <p class="jirau-dialog__eyebrow">Jirau</p>
                <h2 id="jirau-create-board-title">{{ $lockProject ? 'Adicionar quadro' : 'Novo quadro' }}</h2>
            </div>
            <button type="button" class="jirau-dialog__close" data-jirau-dialog-close aria-label="Fechar">✕</button>
        </div>
        <form method="POST" action="{{ route('jirau.boards.store') }}" class="jirau-dialog__form" data-jirau-create-board>
            @csrf
            <input type="hidden" name="_board_create" value="1">
            @if($lockProject)
                <input type="hidden" name="project_id" value="{{ $selectedProject?->id }}" data-jirau-board-project data-company-id="{{ $selectedProject?->company_id }}">
                <p>Quadro para <strong>{{ $selectedProject?->name }}</strong>. O mesmo projeto pode ter um Jirau em cada empresa ou contrato.</p>
            @elseif($createBoardProjects->isEmpty())
                <p>Cadastre um projeto primeiro — o quadro nasce ligado a ele.</p>
                <a href="{{ route('admin.projects.index') }}" class="pm-btn pm-btn--primary">Ir aos projetos</a>
            @else
                <p>Escolha o projeto, a empresa/contrato e o modelo. Cada quadro concentra as atividades daquela alocação.</p>
                <label class="block text-sm">
                    <span class="text-mist">Projeto</span>
                    <select name="project_id" class="jirau-input mt-1.5 w-full" required data-jirau-board-project>
                        <option value="">Selecione…</option>
                        @foreach(\App\Enums\ProjectStatus::boardOrder() as $pickerStatus)
                            @php
                                $bucket = $createBoardProjects->filter(fn ($pickerProject) => $pickerProject->status?->value === $pickerStatus);
                            @endphp
                            @continue($bucket->isEmpty())
                            <optgroup label="{{ \App\Enums\ProjectStatus::from($pickerStatus)->label() }}">
                                @foreach($bucket as $pickerProject)
                                    <option
                                        value="{{ $pickerProject->id }}"
                                        data-company-id="{{ $pickerProject->company_id }}"
                                        @selected((int) $pickerProject->id === $selectedProjectId)
                                    >
                                        {{ $pickerProject->name }}
                                        @if(($pickerProject->jiraus_count ?? 0) > 0)
                                            · {{ $pickerProject->jiraus_count }} quadro{{ $pickerProject->jiraus_count === 1 ? '' : 's' }}
                                        @endif
                                    </option>
                                @endforeach
                            </optgroup>
                        @endforeach
                    </select>
                </label>
            @endif
            @if($lockProject || $createBoardProjects->isNotEmpty())
                <label class="block text-sm">
                    <span class="text-mist">Empresa</span>
                    <select name="company_id" class="jirau-input mt-1.5 w-full" data-jirau-board-company>
                        <option value="">Empresa do projeto</option>
                        @foreach($createBoardCompanies as $pickerCompany)
                            <option value="{{ $pickerCompany->id }}" @selected((int) $pickerCompany->id === $selectedCompanyId)>
                                {{ $pickerCompany->displayName() }}
                                @if($pickerCompany->status !== \App\Enums\CompanyStatus::Active)
                                    · {{ $pickerCompany->status->label() }}
                                @endif
                            </option>
                        @endforeach
                    </select>
                </label>
                <x-ui.input name="contract_name" label="Contrato (opcional)" />
                <x-ui.input name="board_name" label="Nome do quadro (opcional)" />
                <fieldset class="jirau-templates">
                    <legend>Modelo</legend>
                    @foreach(\App\Enums\JirauTemplate::cases() as $template)
                        <label class="jirau-template">
                            <input
                                type="radio"
                                name="template"
                                value="{{ $template->value }}"
                                @checked(old('template', \App\Enums\JirauTemplate::Development->value) === $template->value)
                                required
                            >
                            <span>
                                <strong>{{ $template->label() }}</strong>
                                <small>{{ $template->description() }}</small>
                            </span>
                        </label>
                    @endforeach
                </fieldset>
                <button type="submit" class="pm-btn pm-btn--primary">{{ $submitLabel }}</button>
            @endif
        </form>
    </div>
</div>

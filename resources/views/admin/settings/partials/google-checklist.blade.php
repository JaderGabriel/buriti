@php
    $connection = $googleConnection ?? [
        'state' => 'missing_credentials',
        'has_client_id' => false,
        'has_secret' => false,
        'has_refresh' => false,
    ];
    $integration = $googleIntegration ?? [];
    $autoSync = in_array(($settings['google_auto_sync'] ?? '0'), ['1', 'true', 'on', 'yes'], true);
    $calendarIdOk = filled($googleResolvedCalendarId ?? null)
        && ($googleResolvedCalendarId ?? '') !== '';
    $calendarAligned = ($integration['calendar_matches_embed'] ?? null) !== false && $calendarIdOk;
    $apiReady = (bool) ($googleApiReady ?? false);
    $hasCredentials = ! empty($connection['has_client_id']) && ! empty($connection['has_secret']);
    $hasRefresh = ! empty($connection['has_refresh']);

    $checks = [
        [
            'done' => true,
            'label' => 'URI de callback no Cloud Console',
            'hint' => $googleRedirectUri ?? (rtrim(config('app.url'), '/').'/admin/google/callback'),
        ],
        [
            'done' => $hasCredentials,
            'label' => 'Client ID + Secret salvos',
            'hint' => $hasCredentials ? 'Credenciais detectadas' : 'Cole e salve à esquerda',
        ],
        [
            'done' => $hasRefresh,
            'label' => 'Conta Google ligada',
            'hint' => $hasRefresh ? 'Refresh token gravado' : 'Clique em «Ligar conta Google»',
        ],
        [
            'done' => $calendarIdOk,
            'label' => 'Calendar ID da agenda correcta',
            'hint' => $calendarAligned
                ? ($googleResolvedCalendarId ?? 'ok')
                : 'Confirme o ID (deve coincidir com o embed)',
        ],
        [
            'done' => $autoSync,
            'label' => 'Sincronizar automaticamente',
            'hint' => $autoSync ? 'Activo' : 'Marque a opção e salve',
        ],
        [
            'done' => $apiReady,
            'label' => 'API pronta (sync + Meet no CRM)',
            'hint' => $apiReady ? 'Pode criar eventos sem sair do site' : 'Complete os passos acima',
        ],
    ];

    $doneCount = collect($checks)->where('done', true)->count();
    $totalCount = count($checks);
    $progress = $totalCount > 0 ? (int) round(($doneCount / $totalCount) * 100) : 0;
@endphp

<details class="admin-docs__details admin-docs__details--checklist" open>
    <summary>
        Checklist rápido (nível 3)
        <span class="admin-docs__checklist-badge">{{ $doneCount }}/{{ $totalCount }}</span>
    </summary>

    <div class="admin-docs__checklist-progress" aria-hidden="true">
        <span style="width: {{ $progress }}%"></span>
    </div>

    <ul class="admin-docs__checklist">
        @foreach($checks as $check)
            <li class="{{ $check['done'] ? 'is-done' : 'is-pending' }}">
                <span class="admin-docs__checklist-mark" aria-hidden="true"></span>
                <span class="admin-docs__checklist-copy">
                    <span class="admin-docs__checklist-label">{{ $check['label'] }}</span>
                    @if(!empty($check['hint']))
                        <span class="admin-docs__checklist-hint">{{ $check['hint'] }}</span>
                    @endif
                </span>
            </li>
        @endforeach
    </ul>

    <p class="admin-docs__note admin-docs__note--tight">
        No Cloud Console: active também a <strong>Google Calendar API</strong> e, em modo teste, o seu Gmail em utilizadores de teste.
    </p>
</details>

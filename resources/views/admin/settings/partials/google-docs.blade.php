<x-admin.inline-docs title="Passo a passo da integração Google" class="admin-docs--sticky">
    <p>A integração tem <strong>3 níveis</strong>. Os campos à esquerda cobrem os níveis 1–2; o nível 3 usa o <code>.env</code>.</p>

    <details class="admin-docs__details" open>
        <summary>Nível 1 — URL da Agenda (atalhos)</summary>
        <ol>
            <li>Abra <a href="https://calendar.google.com/" target="_blank" rel="noopener">Google Agenda</a>.</li>
            <li>Copie a URL principal, ex.: <code>https://calendar.google.com/calendar/u/0/r</code></li>
            <li>Cole em <strong>URL da agenda</strong> e salve.</li>
        </ol>
        <p class="admin-docs__note">Resultado: botões “Google Agenda” / “Novo Meet” nas tarefas. Sem API, o sync abre um template no browser.</p>
    </details>

    <details class="admin-docs__details">
        <summary>Nível 2 — Embed no painel</summary>
        <ol>
            <li>Na Agenda: engrenagem → agenda → <strong>Integrar calendário</strong>.</li>
            <li>Copie a URL do iframe (<code>calendar.google.com/calendar/embed?src=...</code>) ou o HTML completo.</li>
            <li>Cole em <strong>Embed</strong> (só domínios Google são aceites).</li>
            <li>Em Acesso da agenda, use “Disponível publicamente” (ou só disponibilidade).</li>
            <li>Salve e abra <a href="{{ route('admin.tasks.index') }}">Admin → Agenda</a>.</li>
        </ol>
    </details>

    <details class="admin-docs__details">
        <summary>Nível 3 — API OAuth (sync + Meet)</summary>
        <p>A app usa <strong>refresh token</strong> (ainda não há botão “Ligar com Google”).</p>
        <p class="admin-docs__label">Variáveis no <code>.env</code></p>
        <pre class="admin-docs__code">GOOGLE_CLIENT_ID=...apps.googleusercontent.com
GOOGLE_CLIENT_SECRET=GOCSPX-...
GOOGLE_REFRESH_TOKEN=1//...
GOOGLE_REDIRECT_URI="${APP_URL}/admin/google/callback"</pre>
        <p class="admin-docs__label">Cloud Console</p>
        <ol>
            <li>Em <a href="https://console.cloud.google.com/" target="_blank" rel="noopener">Google Cloud Console</a>, crie/selecione um projeto.</li>
            <li>Ative <strong>Google Calendar API</strong>.</li>
            <li>Tela de consentimento OAuth (Externo) com scopes:
                <code>calendar</code> e <code>calendar.events</code>; em modo teste, adicione o seu Gmail.</li>
            <li>Credenciais → cliente OAuth <strong>Aplicativo da Web</strong>, com URIs:
                <code>https://developers.google.com/oauthplayground</code>
                e <code>{{ rtrim(config('app.url'), '/') }}/admin/google/callback</code>.</li>
            <li>Copie Client ID e Secret para o <code>.env</code>.</li>
        </ol>
        <p class="admin-docs__label">Refresh token (Playground)</p>
        <ol>
            <li>Abra <a href="https://developers.google.com/oauthplayground/" target="_blank" rel="noopener">OAuth 2.0 Playground</a>.</li>
            <li>Engrenagem → “Use your own OAuth credentials” → cole ID e Secret.</li>
            <li>Step 1 → Calendar API → scopes <code>calendar</code> / <code>calendar.events</code> → Authorize.</li>
            <li>Step 2 → Exchange → copie o <strong>Refresh token</strong> para o <code>.env</code>.</li>
            <li>Rode <code>php artisan config:clear</code>.</li>
        </ol>
        <p class="admin-docs__label">Calendar ID + auto-sync</p>
        <ol>
            <li>Agenda → Integrar calendário → copie o <strong>ID</strong> (<code>primary</code> ou e-mail da agenda).</li>
            <li>Cole em <strong>Calendar ID (API)</strong>.</li>
            <li>Marque <strong>Sincronizar automaticamente</strong> e salve.</li>
        </ol>
        <p class="admin-docs__note">Fuso dos eventos: <code>APP_TIMEZONE=America/Sao_Paulo</code> (GMT-3).</p>
    </details>

    <details class="admin-docs__details">
        <summary>Checklist rápido (nível 3)</summary>
        <ul class="admin-docs__checklist">
            <li>Calendar API ativa</li>
            <li>OAuth Client + Secret no <code>.env</code></li>
            <li>Refresh token com scope calendar</li>
            <li><code>php artisan config:clear</code></li>
            <li>Calendar ID preenchido</li>
            <li>Auto-sync ligado</li>
            <li>Painel mostra “API pronta: sim”</li>
        </ul>
    </details>
</x-admin.inline-docs>

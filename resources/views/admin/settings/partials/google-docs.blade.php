<x-admin.inline-docs title="Passo a passo da integração Google" class="admin-docs--sticky">
    <p>A integração tem <strong>3 níveis</strong>. Com o nível 3, criar eventos e Meet fica <strong>dentro do CRM</strong> — sem abrir o Google Agenda para terminar.</p>

    <details class="admin-docs__details" open>
        <summary>Nível 1 — URL da Agenda (atalhos)</summary>
        <ol>
            <li>Abra <a href="https://calendar.google.com/" target="_blank" rel="noopener">Google Agenda</a>.</li>
            <li>Copie a URL principal, ex.: <code>https://calendar.google.com/calendar/u/0/r</code></li>
            <li>Cole em <strong>URL da agenda</strong> e salve.</li>
        </ol>
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

    <details class="admin-docs__details" open>
        <summary>Nível 3 — API OAuth (sync + Meet no CRM)</summary>
        <ol>
            <li>Em <a href="https://console.cloud.google.com/" target="_blank" rel="noopener">Google Cloud Console</a>, crie/selecione um projeto.</li>
            <li>Ative <strong>Google Calendar API</strong>.</li>
            <li>Tela de consentimento OAuth (Externo); em modo teste, adicione o seu Gmail em <strong>Utilizadores de teste</strong>.</li>
            <li>Credenciais → cliente OAuth <strong>Aplicativo da Web</strong>.</li>
            <li>Em <strong>URIs de redirecionamento autorizados</strong>, adicione exactamente:
                <code class="break-all">{{ $googleRedirectUri ?? (rtrim(config('app.url'), '/').'/admin/google/callback') }}</code>
            </li>
            <li>Copie Client ID e Secret para os campos à esquerda (ou para o <code>.env</code>) e <strong>Salve</strong>.</li>
            <li>Clique em <strong>Ligar conta Google</strong>, autorize Calendar, e o Google devolve ao CRM.</li>
            <li>Preencha <strong>Calendar ID</strong> (<code>primary</code> ou o ID da agenda), marque <strong>Sincronizar automaticamente</strong> e salve.</li>
        </ol>
        <p class="admin-docs__note">
            Depois disso, ao criar uma tarefa com Meet, o CRM cria o evento pela API, gera o link Meet e guarda em <code>meet_url</code> — sem sair do site.
        </p>
        <p class="admin-docs__note">
            Se aparecer <strong>Erro 400: redirect_uri_mismatch</strong>, a URI no Console tem de coincidir com a mostrada acima (incluindo <code>/public</code> se estiver no APP_URL).
        </p>
        <p class="admin-docs__note">Fuso dos eventos: <code>APP_TIMEZONE=America/Sao_Paulo</code>.</p>
    </details>

    <details class="admin-docs__details">
        <summary>Checklist rápido (nível 3)</summary>
        <ul class="admin-docs__checklist">
            <li>Calendar API ativa</li>
            <li>URI de callback registada no Cloud Console</li>
            <li>Client ID + Secret salvos</li>
            <li>Conta Google ligada (botão no painel)</li>
            <li>Calendar ID preenchido</li>
            <li>Auto-sync ligado</li>
            <li>Painel mostra “API pronta: sim”</li>
        </ul>
    </details>
</x-admin.inline-docs>

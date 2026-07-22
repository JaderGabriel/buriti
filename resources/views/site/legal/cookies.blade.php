@extends('layouts.site')

@section('title', 'Cookies — BURI-TI')

@section('content')
    <section class="section-shell">
        <div class="mx-auto max-w-3xl px-4 sm:px-6 lg:px-8">
            <p class="section-kicker">Legal · LGPD</p>
            <h1 class="section-title">Política de Cookies</h1>
            <p class="mt-2 text-sm text-mist">Última atualização: {{ $updatedAt }}</p>

            <div class="mt-10 space-y-8 text-sm leading-relaxed text-mist sm:text-base">
                <div>
                    <h2 class="font-display text-lg font-semibold text-snow">1. O que são cookies</h2>
                    <p class="mt-3">
                        Cookies são pequenos ficheiros guardados no seu navegador. Também usamos armazenamento local (localStorage)
                        para preferências da interface. Nesta página, “cookies” inclui esses mecanismos equivalentes.
                    </p>
                </div>

                <div>
                    <h2 class="font-display text-lg font-semibold text-snow">2. O que utilizamos</h2>
                    <div class="mt-4 overflow-x-auto rounded-sm border border-line">
                        <table class="min-w-full text-left text-sm">
                            <thead class="border-b border-line bg-ink/40 text-xs uppercase tracking-wide text-mist">
                                <tr>
                                    <th class="px-3 py-3 font-semibold">Nome / tipo</th>
                                    <th class="px-3 py-3 font-semibold">Finalidade</th>
                                    <th class="px-3 py-3 font-semibold">Duração</th>
                                    <th class="px-3 py-3 font-semibold">Necessário</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-line">
                                <tr>
                                    <td class="px-3 py-3 text-snow">Sessão Laravel</td>
                                    <td class="px-3 py-3">Manter sessão autenticada no painel admin e proteção CSRF</td>
                                    <td class="px-3 py-3">Sessão / conforme configuração do servidor</td>
                                    <td class="px-3 py-3">Sim</td>
                                </tr>
                                <tr>
                                    <td class="px-3 py-3 text-snow">XSRF-TOKEN</td>
                                    <td class="px-3 py-3">Segurança contra pedidos forjados</td>
                                    <td class="px-3 py-3">Sessão</td>
                                    <td class="px-3 py-3">Sim</td>
                                </tr>
                                <tr>
                                    <td class="px-3 py-3 text-snow">buriti-theme (localStorage)</td>
                                    <td class="px-3 py-3">Lembrar preferência de modo claro/escuro</td>
                                    <td class="px-3 py-3">Até limpar dados do navegador</td>
                                    <td class="px-3 py-3">Funcional</td>
                                </tr>
                                <tr>
                                    <td class="px-3 py-3 text-snow">buriti-cookie-consent (localStorage)</td>
                                    <td class="px-3 py-3">Registar o seu reconhecimento do aviso de cookies</td>
                                    <td class="px-3 py-3">Até limpar dados do navegador</td>
                                    <td class="px-3 py-3">Sim</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <p class="mt-4">
                        Neste momento <strong class="text-snow">não</strong> utilizamos cookies de publicidade, remarketing ou analytics de terceiros
                        (ex.: Google Analytics, Meta Pixel).
                    </p>
                </div>

                <div>
                    <h2 class="font-display text-lg font-semibold text-snow">3. Base legal</h2>
                    <p class="mt-3">
                        Cookies <strong class="text-snow">estritamente necessários</strong> ao funcionamento e à segurança do site
                        são utilizados com base no legítimo interesse e na execução do serviço solicitado.
                        Preferências de interface (tema) são opcionais e podem ser limpas no navegador.
                    </p>
                </div>

                <div>
                    <h2 class="font-display text-lg font-semibold text-snow">4. Como gerir</h2>
                    <ul class="mt-3 list-disc space-y-2 pl-5">
                        <li>Pode aceitar o aviso de cookies no banner exibido na primeira visita.</li>
                        <li>Pode limpar cookies e localStorage nas definições do navegador a qualquer momento.</li>
                        <li>Desativar cookies necessários pode impedir o login no painel admin.</li>
                    </ul>
                </div>

                <div>
                    <h2 class="font-display text-lg font-semibold text-snow">5. Mais informações</h2>
                    <p class="mt-3">
                        Consulte também a
                        <a href="{{ route('privacy') }}" class="text-brand-bright hover:underline">Política de Privacidade</a>.
                        Dúvidas:
                        <a href="mailto:{{ $privacyEmail }}" class="text-brand-bright hover:underline">{{ $privacyEmail }}</a>.
                    </p>
                </div>
            </div>
        </div>
    </section>
@endsection

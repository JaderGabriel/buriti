@extends('layouts.site')

@section('title', 'Privacidade — BURI-TI')

@section('content')
    <section class="section-shell">
        <div class="mx-auto max-w-3xl px-4 sm:px-6 lg:px-8">
            <p class="section-kicker">Legal · LGPD</p>
            <h1 class="section-title">Política de Privacidade</h1>
            <p class="mt-2 text-sm text-mist">Última atualização: {{ $updatedAt }}</p>

            <div class="mt-10 space-y-8 text-sm leading-relaxed text-mist sm:text-base">
                <div>
                    <h2 class="font-display text-lg font-semibold text-snow">1. Quem somos</h2>
                    <p class="mt-3">
                        A BURI-TI — Tecnologia para Pessoas (“BURI-TI”, “nós”) é a controladora dos dados pessoais tratados neste site
                        (<a href="https://buriti.dev.br" class="text-brand-bright hover:underline">buriti.dev.br</a>
                        e no painel administrativo associado.
                    </p>
                    <p class="mt-3">
                        Contato para assuntos de privacidade:
                        <a href="mailto:{{ $privacyEmail }}" class="text-brand-bright hover:underline">{{ $privacyEmail }}</a>.
                    </p>
                </div>

                <div>
                    <h2 class="font-display text-lg font-semibold text-snow">2. Quais dados coletamos</h2>
                    <ul class="mt-3 list-disc space-y-2 pl-5">
                        <li><strong class="text-snow">Formulário de contato:</strong> nome, e-mail, telefone/WhatsApp, empresa (opcional), assunto, mensagem e canal preferido.</li>
                        <li><strong class="text-snow">Dados técnicos mínimos:</strong> endereço IP e registos de segurança em tentativas de login no painel admin.</li>
                        <li><strong class="text-snow">Cookies e armazenamento local:</strong> ver a <a href="{{ route('cookies') }}" class="text-brand-bright hover:underline">Política de Cookies</a>.</li>
                    </ul>
                </div>

                <div>
                    <h2 class="font-display text-lg font-semibold text-snow">3. Para que usamos os dados</h2>
                    <ul class="mt-3 list-disc space-y-2 pl-5">
                        <li>Responder pedidos comerciais e de suporte enviados pelo site.</li>
                        <li>Gerir o relacionamento comercial (CRM interno), quando aplicável.</li>
                        <li>Garantir segurança, autenticação e funcionamento do painel administrativo.</li>
                        <li>Cumprir obrigações legais e defender direitos em processos eventualmente necessários.</li>
                    </ul>
                    <p class="mt-3">Não vendemos dados pessoais. Não utilizamos os dados do formulário para spam.</p>
                </div>

                <div>
                    <h2 class="font-display text-lg font-semibold text-snow">4. Base legal (LGPD)</h2>
                    <p class="mt-3">
                        Tratamos dados com base no <strong class="text-snow">consentimento</strong> (quando marcado no formulário),
                        na <strong class="text-snow">execução de procedimentos preliminares</strong> a contrato a pedido do titular
                        e no <strong class="text-snow">legítimo interesse</strong> para segurança do site e do painel,
                        sempre com proporcionalidade e minimização.
                    </p>
                </div>

                <div>
                    <h2 class="font-display text-lg font-semibold text-snow">5. Compartilhamento</h2>
                    <p class="mt-3">
                        Podemos utilizar prestadores de infraestrutura (hospedagem, e-mail e ferramentas de operação) como operadores,
                        apenas para as finalidades descritas. Dados não são partilhados com terceiros para marketing sem consentimento.
                    </p>
                </div>

                <div>
                    <h2 class="font-display text-lg font-semibold text-snow">6. Conservação</h2>
                    <p class="mt-3">
                        Mantemos os dados pelo tempo necessário ao atendimento do pedido, à gestão comercial e a obrigações legais.
                        Pedidos de exclusão ou anonimização podem ser feitos pelo e-mail de privacidade, observados limites legais.
                    </p>
                </div>

                <div>
                    <h2 class="font-display text-lg font-semibold text-snow">7. Direitos do titular</h2>
                    <p class="mt-3">Nos termos da LGPD, você pode solicitar:</p>
                    <ul class="mt-3 list-disc space-y-2 pl-5">
                        <li>confirmação de tratamento e acesso aos dados;</li>
                        <li>correção de dados incompletos ou desatualizados;</li>
                        <li>anonimização, bloqueio ou eliminação de dados desnecessários;</li>
                        <li>portabilidade, quando aplicável;</li>
                        <li>informação sobre compartilhamentos;</li>
                        <li>revogação do consentimento.</li>
                    </ul>
                    <p class="mt-3">
                        Para exercer direitos, escreva para
                        <a href="mailto:{{ $privacyEmail }}" class="text-brand-bright hover:underline">{{ $privacyEmail }}</a>
                        com o assunto “LGPD”.
                    </p>
                </div>

                <div>
                    <h2 class="font-display text-lg font-semibold text-snow">8. Segurança</h2>
                    <p class="mt-3">
                        Adotamos medidas técnicas e organizacionais razoáveis (HTTPS, controlo de acesso ao painel, proteção de senhas).
                        Nenhum sistema é 100% isento de risco; em caso de incidente relevante, adotaremos as medidas previstas na legislação.
                    </p>
                </div>

                <div>
                    <h2 class="font-display text-lg font-semibold text-snow">9. Atualizações</h2>
                    <p class="mt-3">
                        Esta política pode ser atualizada. A data no topo indica a versão vigente.
                        Alterações relevantes serão refletidas nesta página.
                    </p>
                </div>
            </div>
        </div>
    </section>
@endsection

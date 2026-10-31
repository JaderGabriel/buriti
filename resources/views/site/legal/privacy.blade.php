@extends('layouts.site')

@section('title', 'Privacidade — '.$product['name'])

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
                        <li><strong class="text-snow">Formulário de contato:</strong> nome, e-mail, telefone/WhatsApp, empresa (opcional), assunto, mensagem, canal preferido e o consentimento marcado no envio.</li>
                        <li><strong class="text-snow">CRM interno:</strong> os mesmos dados do formulário (e atualizações posteriores) na ficha de contato, empresa, oportunidade, projeto, tarefa, atividade e anexos — para conduzir o atendimento comercial.</li>
                        <li><strong class="text-snow">Painel (admin e Jirau):</strong> identificação da conta, senha (hash), foto de perfil, identificador de chat Telegram quando vinculado, credencial pública de chave de acesso (passkey / WebAuthn; a chave privada fica no aparelho), segredo TOTP das Senhas do iPhone (cifrado) quando ativado, e tokens OAuth Google (Calendar/Drive) se a conta for ligada.</li>
                        <li><strong class="text-snow">Segurança:</strong> tentativas de login (identificador, IP, user-agent), sessões e registros de auditoria de ações no painel.</li>
                        <li><strong class="text-snow">Cookies e armazenamento local:</strong> ver a <a href="{{ route('cookies') }}" class="text-brand-bright hover:underline">Política de Cookies</a>.</li>
                    </ul>
                </div>

                <div>
                    <h2 class="font-display text-lg font-semibold text-snow">3. Para que usamos os dados</h2>
                    <ul class="mt-3 list-disc space-y-2 pl-5">
                        <li>Responder pedidos comerciais e de suporte enviados pelo site.</li>
                        <li>Gerenciar o relacionamento no CRM (mensagem → contato → oportunidade → projeto) e no quadro Jirau, quando houver entrega.</li>
                        <li>Autenticar o painel: senha, chave de acesso (Face ID / passkey no dispositivo), código 2FA (Senhas do iPhone ou Telegram) e desafio temporário no bot.</li>
                        <li>Sincronizar agenda e arquivos com Google Calendar / Drive, só se um administrador ligar a conta.</li>
                        <li>Avisar operadores no Telegram (novo lead, lembrete de tarefa, follow-up, eventos do Jirau), quando o bot estiver configurado.</li>
                        <li>Cumprir obrigações legais e defender direitos em processos eventualmente necessários.</li>
                    </ul>
                    <p class="mt-3">Não vendemos dados pessoais. Não utilizamos os dados do formulário para spam.</p>
                </div>

                <div>
                    <h2 class="font-display text-lg font-semibold text-snow">4. Base legal (LGPD)</h2>
                    <p class="mt-3">
                        Tratamos dados com base no <strong class="text-snow">consentimento</strong> (quando marcado no formulário),
                        na <strong class="text-snow">execução de procedimentos preliminares</strong> a contrato a pedido do titular,
                        no <strong class="text-snow">legítimo interesse</strong> para segurança do site e do painel,
                        e no <strong class="text-snow">cumprimento de obrigação legal</strong> quando aplicável,
                        sempre com proporcionalidade e minimização.
                    </p>
                </div>

                <div>
                    <h2 class="font-display text-lg font-semibold text-snow">5. Compartilhamento</h2>
                    <p class="mt-3">
                        Usamos operadores apenas para as finalidades descritas, sem ceder dados para marketing de terceiros:
                    </p>
                    <ul class="mt-3 list-disc space-y-2 pl-5">
                        <li><strong class="text-snow">Hospedagem e e-mail</strong> da infraestrutura onde o site e o painel correm.</li>
                        <li><strong class="text-snow">Telegram</strong> (Bot API), quando um administrador vincula o chat: códigos 2FA, desafios de login e avisos operacionais que podem incluir nome, telefone, e-mail ou assunto de um lead/tarefa.</li>
                        <li><strong class="text-snow">Google</strong> (Calendar e Drive), só se um administrador ligar a conta OAuth no painel.</li>
                    </ul>
                    <p class="mt-3">
                        Códigos 2FA e desafios de login são de curta duração. O Telegram e o Google tratam dados segundo as respectivas políticas, em servidores que podem ficar fora do Brasil.
                    </p>
                </div>

                <div>
                    <h2 class="font-display text-lg font-semibold text-snow">6. Conservação</h2>
                    <p class="mt-3">
                        Mantemos os dados do CRM pelo tempo da relação comercial e das obrigações legais.
                        Códigos 2FA e desafios de login expiram em minutos.
                        Tentativas de login e logs de auditoria ficam para investigação de segurança; não há apagamento automático publicado.
                        Pedidos de exclusão ou anonimização podem ser feitos pelo e-mail de privacidade, observados limites legais (ex.: obrigações fiscais ou defesa em processo).
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
                        Adotamos medidas técnicas e organizacionais razoáveis (HTTPS, controlo de acesso ao painel, hash de senhas, chave de acesso WebAuthn, 2FA no login admin, TOTP encriptado).
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

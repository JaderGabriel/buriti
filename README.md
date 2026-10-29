# BURI-TI

Site institucional e painel administrativo da **BURI-TI** — *Tecnologia para Pessoas*. Sistema **1.10 Porto** (site, CRM e Jirau). A versão visível no UI vem de `config/buriti.php` via `RuntimeVersions`.

Stack: **Laravel 13**, **Blade**, **Vite**, **Tailwind CSS 4**, **Alpine.js**, MySQL.

- Site: [buriti.dev.br](https://buriti.dev.br)
- GitHub Pages (currículo): [jadergabriel.github.io](https://jadergabriel.github.io/)
- GitHub: [JaderGabriel](https://github.com/JaderGabriel)
- LinkedIn: [jadergabriel](https://www.linkedin.com/in/jadergabriel/)

Documentação de consolidação do CRM: [`docs/CRM-CONSOLIDACAO.md`](docs/CRM-CONSOLIDACAO.md). Roadmap de módulos e impacto: [`docs/ROADMAP.md`](docs/ROADMAP.md) (leitura no admin: Sistema → Roadmap).

## Funcionalidades

### Site público
- Landing com marca, serviços de TI, portfólio e CTA
- Formulário de contacto (honeypot + rate limit) → mensagens no admin (pode gerar lead)
- Páginas legais (privacidade, cookies)
- Canais: e-mail, WhatsApp, Telegram, LinkedIn e GitHub
- Layout responsivo com menu mobile

### CRM comercial (`/admin`)

Jornada: **Mensagem → Contato → Oportunidade → Projeto → Tarefa**.

| Área | O que faz |
|---|---|
| **Dashboard** | KPIs com tendência, mensagens, contactos, agenda, atividades recentes, ideias (post-its); atalhos Meet / Teams / Jitsi |
| **Mensagens** | Inbox do formulário público; vínculo a contacto / lead |
| **Correio** | Cliente IMAP da conta SMTP (só admin): pastas, etiquetas, marcadores, ligações CRM; compositor HTML (Para/Cc/Cco em chips com autocomplete de contatos, formatação, imagens inline CID, galeria de anexos); avisos de e-mail recebido/respondido; assinatura e aviso LGPD; cópia em Enviados |
| **Empresas** | Cadastro de clientes/organizações |
| **Contatos** | Agenda telefónica + ficha (hero, timeline, pasta de arquivos, mini-calendário); telefone em E.164 (`tel:` e WhatsApp) |
| **Atividades** | Notas, chamadas, reuniões, e-mails; vínculo opcional a tarefa/oportunidade; conclusão de reunião **só se marcada** |
| **Oportunidades** | Pipeline por estágio (board) e valor |
| **Projetos** | Board com ordenação vertical, etapas, logo/contrato, anexos |
| **Jirau** | Quadro ágil por projeto (Demandas → Feito), etiquetas, pontos, WIP, checklist, comentários; membros do projeto só veem o quadro e abrem demandas |
| **Tarefas / agenda** | Calendário, agenda, quadro, lista; Meet / Teams / Jitsi; sync Google; cores; ICS |
| **Anexos** | Soft delete (lixeira), preview no browser (PDF/imagem/média/texto), download |
| **Usuários** | CRUD, avatar, ativar/desativar, sessões com revogação |
| **Perfil** | Dados próprios, sessões, chave de acesso (passkey / Face ID) e 2FA (Senhas do iPhone ou Telegram) |
| **Integrações** | Google, Telegram Bot, Trello e Notion — **credenciais cifradas na base**, não no Git |
| **Configurações** | Contactos do site, e-mail SMTP/IMAP (senha cifrada) e hub Google (níveis 1–3) |

## Segurança

- Middleware `auth` no painel; apenas `is_admin` acessa o admin. Membros de um Jirau entram em `/jirau` sem CRM
- Headers de segurança (CSP, X-Frame-Options, nosniff, Referrer-Policy)
- Throttle no login (`5/min`) e no formulário público
- Hash de senha, regeneração de sessão no login/logout
- Histórico de tentativas de login e revogação de sessões (`SESSION_DRIVER=database`)
- Senha mínima com letras e números (regra `Password::defaults`)
- Honeypot no contacto público
- Login admin por usuário/senha exige 2FA: TOTP nas **Senhas do iPhone** se o método estiver vinculado; senão, código de 6 dígitos no Telegram
- **Chave de acesso (passkey)** cadastra-se só no perfil, já autenticado. No Safari/iOS o próximo login pode usar Face ID sem o código; outros dispositivos continuam com senha + código
- Login admin também via desafio temporário no bot Telegram (sem widget de terceiros na página)
- Membro de Jirau autentica só com usuário/senha (sem Telegram) e não acessa o CRM
- Telefones normalizados em E.164 (DDI + DDD + número) em `tel:` e `wa.me` no CRM e no bot
- **Segredos operacionais** (SMTP/IMAP, Google, Telegram, Trello, Notion) ficam na tabela `settings`, cifrados com `APP_KEY`. O `.env` só serve de bootstrap opcional e **não deve ser commitado**. Contactos públicos editam-se em Admin → Configurações, não em ficheiros do repositório.

A política pública (CRM, Telegram, Google, 2FA) está em `/privacidade`. A página descreve o tratamento actual; não substitui um DPIA.

## Requisitos

- PHP **8.3+** com extensões: `pdo_mysql` (ou `pdo_sqlite` para testes/local)
- Composer 2
- Node.js 20+ e npm
- MySQL / MariaDB

> Neste ambiente, use `./bin/php` e `./bin/test` para carregar SQLite local sem instalar a extensão no sistema.

## Instalação

```bash
git clone <repo> buriti
cd buriti
composer install
cp .env.example .env
php artisan key:generate   # ou: ./bin/php artisan key:generate
```

Configure o MySQL no `.env`:

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=buriti_site
DB_USERNAME=buriti_buriti
DB_PASSWORD="sua-senha"
```

Depois:

```bash
php artisan migrate --seed
php artisan storage:link
npm install
npm run build
```

## Executar em desenvolvimento

```bash
# terminal 1 — API / Blade
./bin/php artisan serve
# ou: composer run serve

# terminal 2 — Vite (CSS/JS)
npm run dev
```

Abra: [http://127.0.0.1:8000](http://127.0.0.1:8000)

### Acesso admin (após seed)

| Campo | Valor |
|---|---|
| URL | `/admin/login` |
| E-mail | `ADMIN_EMAIL` no `.env` (padrão de seed: `admin@buriti.dev.br`) |
| Senha | `ADMIN_PASSWORD` no `.env` (obrigatório em produção; em local, se vazio: `buriti-local-only`) |

```bash
# só o admin master
./bin/php artisan db:seed --class=AdminSeeder

# seed completo (admin + settings + exemplo)
./bin/php artisan db:seed
```

**Altere a senha em produção.**

### Jirau demonstração (após seed)

Quadro ágil de exemplo com um usuário que só vê aquele projeto:

| Campo | Valor |
|---|---|
| URL | `/admin/login` |
| Usuário | `jirau.demo` |
| E-mail | `jirau.demo@buriti.dev.br` |
| Senha | `jirau-demo-2026` |

```bash
./bin/php artisan db:seed --class=JirauDemoSeeder
```

## Contactos e serviços

E-mail, telefone, WhatsApp, Telegram, LinkedIn e GitHub do **site** editam-se em **Admin → Configurações**. Não ficam em código nem no Git.

Credenciais de **Telegram, Trello, Notion e Google** (tokens, secrets, webhook) editam-se em **Admin → Integrações** / **Configurações**. São gravadas cifradas na base. Depois de gravar no painel, pode retirar as mesmas chaves do `.env`.

## Integração Google Agenda / Meet

Documentação completa no painel: **Admin → Configurações → Integração Google** (`/admin/configuracoes#google-integration`).

| Nível | O que ativa | Onde configurar |
|------|-------------|-----------------|
| **1 — Básico** | Botão “Google Agenda” e atalho para criar evento/Meet | Admin → URL da agenda |
| **2 — Operacional** | Agenda embutida no painel de atividades | Admin → Embed |
| **3 — Total** | API cria eventos + Meet **no CRM** | Client ID/Secret + **Ligar conta Google** + Calendar ID + auto-sync |

<details>
<summary>Referência rápida — nível 3</summary>

1. Registe no Cloud Console a URI: `${APP_URL}/admin/google/callback`
2. Cole Client ID/Secret em **Configurações** e salve (secret cifrado na base)
3. Clique em **Ligar conta Google** (refresh token fica no CRM)
4. Calendar ID + auto-sync

A URI de callback continua a derivar de `APP_URL`. Fuso: `APP_TIMEZONE=America/Sao_Paulo`.
</details>

## Telegram Bot (CRM + Jirau)

Documentação operacional: **Admin → Integrações → Telegram** (`/admin/integracoes#telegram`).

Campos separados por `|`. Em `set`, use `.` para manter. Em `del`, confirme com `ok`.

```text
/ajuda
/login email | senha          # vincula chat a admin (mensagem apagada)
/contatos  ·  /contato …
/oportunidades  ·  /oportunidade …
/projetos  ·  /projeto …
/jiraus  ·  /jirau ID         # quadros ágeis
/cartao ID  ·  /cartao JR-12  # cartão do Jirau
/tarefas  ·  /tarefa …        # agenda: abertas + concluídas recentes
/atividades  ·  /atividade …  # último campo opcional: concluir
/mensagens  ·  /mensagem …
/mensagem responder ID | texto
/correio                       # últimas da INBOX
/email dest | assunto | texto  # envia pela conta SMTP
/cancelar                      # aborta rascunho de e-mail
/card  ·  /card NomeCliente
/id  ·  /eu  ·  /status  ·  /logout
```

### Atividade + reunião

```text
/atividade add contato|tipo|assunto?|corpo?|tarefa?|data?|concluir?
```

Sem `concluir`, a tarefa **só fica vinculada**. Com `concluir` (ou `sim` / `1`), marca a reunião como concluída.

### Card para clientes

Com sessão admin ativa:

```text
/card
/card Acme Educacional
```

Webhook: `POST {APP_URL}/webhooks/telegram/{segredo}` — o segredo grava-se em **Admin → Integrações** (cifrado). O URL completo aparece nesse ecrã.

```bash
php artisan telegram:configure
# Após rate limit no nome, ou para limpar fila/erro 5xx antigo:
php artisan telegram:configure --skip-name --drop-pending
# ou só o webhook:
php artisan telegram:set-webhook
```

Lembretes de tarefas (~10 min antes): `php artisan tasks:telegram-reminders` (via `schedule:run`).

## Arquitetura (MVC)

```
app/
  Enums/                 # Contact, Opportunity, Project, Task, CrmActivity, …
  Http/Controllers/      # Site + Admin (finos)
  Http/Requests/         # Validação
  Models/                # Eloquent + scopes
  Services/              # Settings, Telegram, Google, Attachments, Auth, Audit, …
  Support/               # Helpers (ex.: telefone)
config/buriti.php        # Conteúdo e defaults da marca
resources/views/
  components/            # Blade components (UI, anexos, CRM)
  site/                  # Landing
  admin/                 # Painel
docs/                    # Consolidação CRM + roadmap de produto
```

Fluxo típico: **Request → FormRequest → Controller → Model/Service → View**.

## Testes

Suite PHPUnit (SQLite em memória):

```bash
composer test
# ou
./bin/test
```

Cobertura inclui site público, auth (passkey/Face ID, 2FA iOS + Telegram), CRM (contactos, oportunidades, projetos/board, tarefas, atividades, anexos/preview), Correio IMAP (compositor HTML, anexos, imagens CID), Jirau, Telegram bot, Google/settings, integrações com segredos na BD, roadmap e segurança de sessões.

## Scripts úteis

| Comando | Descrição |
|---|---|
| `composer test` / `./bin/test` | Suite de testes |
| `composer run serve` | Servidor HTTP |
| `npm run dev` / `npm run build` | Vite |
| `php artisan telegram:configure` | Nome, comandos, foto e webhook do bot |
| `php artisan telegram:set-webhook` | Só regista o webhook |
| `php artisan tasks:telegram-reminders` | Lembretes de tarefas |
| `php artisan schedule:run` | Jobs agendados (cron a cada minuto) |

## Deploy (checklist)

1. `APP_ENV=production`, `APP_DEBUG=false`
2. `APP_URL` com o domínio real (idealmente **sem** `/public` no path)
3. MySQL com utilizador limitado à base
4. `php artisan migrate --force`
5. `npm run build` e `php artisan storage:link`
6. `php artisan config:cache && php artisan view:cache`
7. Cron: `* * * * * cd /caminho && php artisan schedule:run`
8. Web root apontando para a pasta `public/` (ideal: URL sem `/public`; se a app ficar em `https://dominio/public`, o guard remove `route:cache` automaticamente)
9. Trocar senha do admin e proteger `/admin`
10. Gravar SMTP/IMAP, Telegram, Google, Trello e Notion no painel (não deixar tokens no `.env` da máquina de deploy se já estiverem na base)

**Nota sobre `route:cache`:** com `APP_URL` em subpath (ex. `https://dominio/public`), o cache de rotas quebra a home (`GET /` → 405, só `HEAD`). A app **remove esse cache ao arrancar** e após `optimize`/`route:cache`. Prefira document root = `public/` e `APP_URL` sem path; aí `route:cache` é seguro. Logout volta para a home do site.

## Licença

Projeto privado da BURI-TI. Todos os direitos reservados.

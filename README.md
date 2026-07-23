# BURI-TI

Site institucional e painel administrativo da **BURI-TI** — *Tecnologia para Pessoas*.

Stack: **Laravel 13**, **Blade**, **Vite**, **Tailwind CSS 4**, **Alpine.js**, MySQL.

- Site: [buriti.dev.br](https://buriti.dev.br)
- GitHub: [JaderGabriel](https://github.com/JaderGabriel)
- LinkedIn: [jadergabriel](https://www.linkedin.com/in/jadergabriel/)

## Funcionalidades

### Site público
- Landing com marca, serviços de TI, portfólio e CTA
- Formulário de contacto (com honeypot + rate limit)
- Canais: e-mail, WhatsApp, Telegram, LinkedIn e GitHub
- Layout responsivo com menu mobile

### Painel admin (`/admin`)
- Dashboard com resumo operacional
- Mensagens do formulário (mensageria visual)
- Projetos (nome, informações, links, GitHub, logo, contrato)
- Anexos com soft delete (lixeira + recuperação) e auditoria
- Planejamento de atividades (calendário / agenda / quadro / lista) com Meet e sync Google
- Usuários (criar/editar), foto de perfil, sessões, login e auditoria
- Integrações: Google, Trello, Notion, Telegram Bot
- Configurações de contato e hub de integração Google (atalhos → embed → API + Meet)

## Segurança

- Middleware `auth` no painel; apenas `is_admin` acessa o admin
- Headers de segurança (CSP, X-Frame-Options, nosniff, Referrer-Policy)
- Throttle no login (`5/min`) e no formulário público
- Hash de senha, regeneração de sessão no login/logout
- Histórico de tentativas de login e revogação de sessões (`SESSION_DRIVER=database`)
- Senha mínima com letras e números (regra `Password::defaults`)
- Honeypot no contato público

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
| E-mail | `ADMIN_EMAIL` no `.env` (padrão: `jadergabriel8@gmail.com`) |
| Senha | `ADMIN_PASSWORD` no `.env` (padrão de desenvolvimento: `buriti2026`) |

```bash
# só o admin master
./bin/php artisan db:seed --class=AdminSeeder

# seed completo (admin + settings + exemplo)
./bin/php artisan db:seed
```

**Altere a senha em produção.**

## Contactos padrão

Definidos em `config/buriti.php` e editáveis em **Admin → Configurações**:

- E-mail: `jadergabriel8@gmail.com`
- WhatsApp: `+55 38991758416`
- Telegram: `@JaderGabriel` → https://t.me/JaderGabriel

## Integração Google Agenda / Meet

A integração tem **3 níveis**. Os níveis 1–2 configuram-se em **Admin → Configurações** (banco `settings`). O nível 3 usa variáveis no **`.env`**.

| Nível | O que ativa | Onde configurar |
|------|-------------|-----------------|
| **1 — Básico** | Botão “Google Agenda” e atalho para criar evento/Meet | Admin → URL da agenda |
| **2 — Operacional** | Agenda embutida no painel de atividades | Admin → Embed |
| **3 — Total** | API cria/atualiza eventos e Meet automaticamente | `.env` + Calendar ID + auto-sync |

Status atual: o painel mostra o nível em **Configurações** e em **Agenda** (`GoogleCalendarService::integrationStatus()`).

---

### Nível 1 — URL da Agenda (atalhos)

1. Abra [Google Agenda](https://calendar.google.com/).
2. Copie a URL da vista principal, tipicamente:
   `https://calendar.google.com/calendar/u/0/r`
3. Em **Admin → Configurações → Integração Google**:
   - **URL da agenda** → cole essa URL
4. Salve.

**Resultado:** botões “Google Agenda” / “Novo Meet” e, sem API, o sync de tarefa abre um *template* de evento no browser (`calendar.google.com/calendar/render?...`).

Não precisa de `.env` neste nível.

---

### Nível 2 — Embed da Agenda no admin

1. No Google Agenda: **Definições** (engrenagem) → escolha a agenda → **Integrar calendário**.
2. Em **Código de inserção**, copie:
   - a **URL** do iframe (`https://calendar.google.com/calendar/embed?src=...`), **ou**
   - o HTML completo `<iframe src="..."></iframe>`
3. Em **Admin → Configurações**:
   - **Embed (URL ou HTML do iframe)** → cole o valor  
   - O sistema aceita só URLs `calendar.google.com` / `www.google.com` (sanitização em `SettingService`).
4. Para a agenda aparecer publicamente no embed, em **Acesso** da agenda use **Disponível publicamente** (ou “Ver apenas disponibilidade”, conforme a política da conta).
5. Salve e abra **Admin → Agenda**.

**Resultado:** iframe da Agenda ao lado das vistas de atividades.

Ainda não precisa de `.env`.

---

### Nível 3 — API OAuth (sync automático + Meet)

O app **não** tem ecrã de “Ligar com Google” ainda. Usa um **refresh token** já obtido e troca-o por *access token* em runtime (`oauth2.googleapis.com/token`).

#### Variáveis no `.env`

```env
GOOGLE_CLIENT_ID=xxxxx.apps.googleusercontent.com
GOOGLE_CLIENT_SECRET=GOCSPX-xxxxx
GOOGLE_REFRESH_TOKEN=1//xxxxx
GOOGLE_REDIRECT_URI="${APP_URL}/admin/google/callback"
```

| Variável | O que é | Onde obter |
|----------|---------|------------|
| `GOOGLE_CLIENT_ID` | ID do cliente OAuth 2.0 | Google Cloud Console → Credenciais |
| `GOOGLE_CLIENT_SECRET` | Segredo do cliente | Mesmo sítio (tipo “Aplicativo da Web”) |
| `GOOGLE_REFRESH_TOKEN` | Token de longa duração com scope Calendar | OAuth Playground (passos abaixo) |
| `GOOGLE_REDIRECT_URI` | URI autorizada no cliente OAuth | Deve coincidir com uma URI registada no Console; o valor default aponta para callback futuro. Para o Playground use também a URI do Playground. |

Depois de editar o `.env`:

```bash
php artisan config:clear
```

#### Passo a passo — Google Cloud Console

1. Aceda a [Google Cloud Console](https://console.cloud.google.com/).
2. Crie (ou selecione) um **projeto**.
3. **APIs e serviços → Biblioteca** → ative **Google Calendar API**.
4. **APIs e serviços → Tela de consentimento OAuth**:
   - Tipo: **Externo** (ou Interno, se for Workspace)
   - Preencha nome da app, e-mail de suporte
   - Em **Scopes**, adicione:
     - `https://www.googleapis.com/auth/calendar`
     - `https://www.googleapis.com/auth/calendar.events`
   - Em modo teste, adicione o seu Gmail em **Utilizadores de teste**
5. **APIs e serviços → Credenciais → Criar credenciais → ID do cliente OAuth**:
   - Tipo: **Aplicativo da Web**
   - **URIs de redirecionamento autorizados** (adicione ambas se for usar o Playground):
     - `https://developers.google.com/oauthplayground`
     - `https://buriti.dev.br/public/admin/google/callback` (ou o seu `APP_URL` + `/admin/google/callback`)
6. Copie **Client ID** e **Client Secret** para o `.env`.

#### Passo a passo — obter `GOOGLE_REFRESH_TOKEN`

1. Abra [OAuth 2.0 Playground](https://developers.google.com/oauthplayground/).
2. Clique na engrenagem (**OAuth 2.0 configuration**):
   - Marque **Use your own OAuth credentials**
   - Cole o `GOOGLE_CLIENT_ID` e o `GOOGLE_CLIENT_SECRET`
3. Em **Step 1**, selecione:
   - `Calendar API v3` → `https://www.googleapis.com/auth/calendar`
   - e/ou `https://www.googleapis.com/auth/calendar.events`
4. **Authorize APIs** → escolha a conta Google da agenda → aceite.
5. **Step 2 → Exchange authorization code for tokens**.
6. Copie o **Refresh token** para `GOOGLE_REFRESH_TOKEN` no `.env`.

> Guarde o refresh token em local seguro. Se o revogar (segurança da conta Google) ou mudar o Client Secret, tem de gerar outro.

#### Calendar ID (Admin, não `.env`)

1. Google Agenda → Definições da agenda → **Integrar calendário**.
2. Copie o **ID do calendário**:
   - agenda principal: muitas vezes `primary`, **ou**
   - um e-mail do tipo `nome@gmail.com` / `...@group.calendar.google.com`
3. Em **Admin → Configurações** → **Calendar ID (API)** → cole o valor (default: `primary`).

#### Auto-sync

Em **Admin → Configurações**, marque:

- **Sincronizar automaticamente ao criar/editar tarefa (requer API nível 3)**

Com API + esta opção:

- criar/editar atividade com Meet marcado → cria/atualiza evento na Agenda e grava `google_event_id` / `meet_url`
- sem API → o botão **Agenda** na atividade abre o template no browser

#### Checklist rápido nível 3

```text
[ ] Calendar API ativada no Cloud Console
[ ] OAuth Client (Web) com Client ID + Secret
[ ] Refresh token com scope calendar no .env
[ ] php artisan config:clear
[ ] Calendar ID correto em Configurações
[ ] Auto-sync ligado (opcional, recomendado)
[ ] Painel mostra “API pronta neste ambiente: sim”
```

#### Notas

- `GOOGLE_REDIRECT_URI` no `.env` serve sobretudo para alinhar a URI no Console; a app **atual** autentica só com refresh token (não implementa ainda a rota `/admin/google/callback`).
- Timezone dos eventos: `APP_TIMEZONE` / `config('app.timezone')`.
- Em produção (`APP_URL=https://buriti.dev.br/public`), mantenha o mesmo domínio nas URIs autorizadas do OAuth.

## Telegram Bot (CRM)

Bot para **listar, ver, criar, editar e apagar** registos do CRM, e receber mensagens do formulário:

1. Crie o bot no [@BotFather](https://t.me/BotFather)
2. No `.env`:
   ```env
   TELEGRAM_BOT_TOKEN=...
   TELEGRAM_BOT_USERNAME=SeuBotSemArroba   # opcional; senão a app resolve via getMe
   TELEGRAM_WEBHOOK_SECRET=uma-string-aleatoria
   ```
3. Com `APP_URL` público (HTTPS), ex.: `https://buriti.dev.br/public`: `php artisan telegram:configure`
4. Fale com o bot e faça login de **admin**: `/login email_ou_usuario | senha` (a mensagem com senha é apagada automaticamente)
5. No site, em `/admin/login`, use **Continuar com Telegram** (abre o bot e confirma o pedido) ou o widget oficial
6. Use `/logout` para sair do bot
7. Comandos: `/ajuda`, `/status`, `/contatos`, CRUD com `add`/`set`/`del`, etc.

Apenas utilizadores com `is_admin=1` e conta ativa podem autenticar. A sessão do bot fica ligada ao `telegram_chat_id` do utilizador — necessário também para o login web via Telegram.

Para o **widget** oficial do Telegram Login, no [@BotFather](https://t.me/BotFather) use `/setdomain` com o domínio do site (ex.: `buriti.dev.br`).

Padrão de ações (campos separados por `|`; em `set` use `.` para manter; em `del` confirme com `ok`):

```text
/contatos
/contato 12
/contato add Nome | email | tel? | empresa? | status?
/contato set 12 | Nome | email | . | empresa | active
/contato del 12 ok
```

O mesmo padrão vale para `/oportunidade`, `/projeto`, `/tarefa` e `/mensagem` (`lida` / `del`).

### Card para clientes

Com sessão admin ativa:

```text
/card
/card Acme Educacional
```

O bot envia uma imagem + legenda + botões (Site, WhatsApp, Pedir proposta, LinkedIn). Encaminhe a mensagem ao cliente no Telegram.

Webhook: `POST {APP_URL}/webhooks/telegram/{TELEGRAM_WEBHOOK_SECRET}`

```bash
php artisan telegram:configure
```

## Arquitetura (MVC)

```
app/
  Enums/                 # ProjectStatus, TaskStatus, TaskPriority
  Http/Controllers/      # Controllers finos (web + admin)
  Http/Requests/         # Form Requests (validação)
  Models/                # Eloquent + scopes
  Services/              # SettingService, TelegramBotService, ProjectFileService
  View/Composers/        # Dados do layout público
config/buriti.php        # Conteúdo e defaults da marca
resources/views/
  components/            # Blade components reutilizáveis
  site/partials/         # Secções da landing
  admin/                 # Painel
```

Fluxo típico: **Request → FormRequest → Controller → Model/Service → View**.

## Testes

Suite PHPUnit (SQLite em memória):

```bash
composer test
# ou
./bin/test
```

Cobertura atual (20 testes):

- Página inicial e portfólio público
- Formulário de contacto (+ honeypot)
- Autenticação admin
- Dashboard, mensagens, projetos, tarefas e settings
- Enums e `SettingService` (sanitização do embed Google)

## Scripts úteis

| Comando | Descrição |
|---|---|
| `composer test` | Corre a suite de testes |
| `composer run serve` | Sobe o servidor HTTP |
| `npm run dev` | Vite em modo desenvolvimento |
| `npm run build` | Build de assets para produção |
| `php artisan telegram:set-webhook` | Regista o webhook do bot Telegram |

## Deploy (checklist)

1. `APP_ENV=production`, `APP_DEBUG=false`
2. `APP_URL` com o domínio real
3. MySQL com utilizador limitado à base
4. `php artisan migrate --force`
5. `npm run build` e `php artisan storage:link`
6. `php artisan config:cache && php artisan route:cache && php artisan view:cache`
7. Web root apontando para `public/`
8. Trocar senha do admin e proteger `/admin`

## Licença

Projeto privado da BURI-TI. Todos os direitos reservados.

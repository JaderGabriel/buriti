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

A documentação completa (níveis 1–3, Cloud Console, Playground, checklist) está no painel:

**Admin → Configurações → Integração Google** (`/admin/configuracoes#google-integration`) — passo a passo ao lado dos campos.

Resumo dos níveis:

| Nível | O que ativa | Onde configurar |
|------|-------------|-----------------|
| **1 — Básico** | Botão “Google Agenda” e atalho para criar evento/Meet | Admin → URL da agenda |
| **2 — Operacional** | Agenda embutida no painel de atividades | Admin → Embed |
| **3 — Total** | API cria eventos + Meet **no CRM** (sem abrir a Agenda) | Client ID/Secret + **Ligar conta Google** + Calendar ID + auto-sync |

Status: o painel mostra o nível em **Configurações** e em **Agenda**.

<details>
<summary>Referência rápida (README) — nível 3</summary>

1. Registe no Cloud Console a URI: `${APP_URL}/admin/google/callback`
2. Cole Client ID/Secret em **Configurações** (ou no `.env`) e salve
3. Clique em **Ligar conta Google** (autorização única; o refresh token fica no CRM)
4. Calendar ID + auto-sync

```env
GOOGLE_CLIENT_ID=xxxxx.apps.googleusercontent.com
GOOGLE_CLIENT_SECRET=GOCSPX-xxxxx
# Opcional se já ligou a conta pelo painel:
# GOOGLE_REFRESH_TOKEN=1//xxxxx
GOOGLE_REDIRECT_URI="${APP_URL}/admin/google/callback"
```

Depois: `php artisan config:clear`. Fuso dos eventos: `APP_TIMEZONE=America/Sao_Paulo`.
</details>

## Telegram Bot (CRM)

Documentação operacional no painel: **Admin → Integrações → Telegram** (`/admin/integracoes#telegram`).

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
| `php artisan tasks:telegram-reminders` | Envia lembretes de tarefas (~10 min antes) |
| `php artisan schedule:run` | Dispara jobs agendados (cron a cada minuto) |

## Deploy (checklist)

1. `APP_ENV=production`, `APP_DEBUG=false`
2. `APP_URL` com o domínio real
3. MySQL com utilizador limitado à base
4. `php artisan migrate --force`
5. `npm run build` e `php artisan storage:link`
6. `php artisan config:cache && php artisan view:cache`
7. Cron: `* * * * * cd /caminho && php artisan schedule:run`
8. Web root apontando para `public/` (URL sem `/public` no path; `APP_URL=https://dominio`)
9. Trocar senha do admin e proteger `/admin`

**Nota sobre `route:cache`:** com a app servida sob um subpath (ex.: `https://dominio/public`), o cache de rotas quebra a home (`GET /` → 405, só `HEAD`). Nesse caso use `php artisan route:clear` e **não** corra `route:cache`. Com document root em `public/`, `route:cache` é seguro.

## Licença

Projeto privado da BURI-TI. Todos os direitos reservados.

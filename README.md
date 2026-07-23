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
- Planejamento de tarefas (kanban) com ícones Meet/Agenda e sync Google
- Usuários (criar/editar), foto de perfil, sessões, login e auditoria
- Integrações: Google, Trello, Notion, Telegram Bot
- Configurações de contato e hub de integração Google (embed → API + Meet)

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

## Telegram Bot (CRM)

Bot automatizado para criar registros e receber mensagens do formulário:

1. Crie o bot no [@BotFather](https://t.me/BotFather)
2. No `.env`:
   ```env
   TELEGRAM_BOT_TOKEN=...
   TELEGRAM_WEBHOOK_SECRET=uma-string-aleatoria
   ```
3. Com `APP_URL` público (HTTPS): `php artisan telegram:set-webhook`
4. Em **Admin → Integrações → Telegram**, cole o Chat ID (`/id` no bot)
5. Comandos: `/ajuda`, `/contato`, `/oportunidade`, `/projeto`, `/tarefa`, `/status`

Webhook: `POST /webhooks/telegram/{TELEGRAM_WEBHOOK_SECRET}`

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

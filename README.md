# BURI-TI

Site institucional e painel administrativo da **BURI-TI** — *Tecnologia para Pessoas*.

Stack: **Laravel 13**, **Blade**, **Vite**, **Tailwind CSS 4**, **Alpine.js**, MySQL.

- Site: [buriti.dev.br](https://buriti.dev.br)
- GitHub: [JaderGabriel](https://github.com/JaderGabriel)
- LinkedIn: [jadergabriel](https://www.linkedin.com/in/jadergabriel/)

Documentação de consolidação do CRM: [`docs/CRM-CONSOLIDACAO.md`](docs/CRM-CONSOLIDACAO.md).

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
| **Dashboard** | KPIs, mensagens, contactos, agenda, atividades recentes, ideias (post-its) |
| **Mensagens** | Inbox do formulário público; vínculo a contacto / lead |
| **Empresas** | Cadastro de clientes/organizações |
| **Contatos** | Agenda telefónica + ficha (hero, timeline, pasta de arquivos, mini-calendário) |
| **Atividades** | Notas, chamadas, reuniões, e-mails; vínculo opcional a tarefa/oportunidade; conclusão de reunião **só se marcada** |
| **Oportunidades** | Pipeline por estágio (board) e valor |
| **Projetos** | Board com ordenação vertical, etapas, logo/contrato, anexos |
| **Tarefas / agenda** | Calendário, agenda, quadro, lista; Meet; sync Google; cores; ICS |
| **Anexos** | Soft delete (lixeira), preview no browser (PDF/imagem/média/texto), download |
| **Usuários** | CRUD, avatar, ativar/desativar, sessões com revogação |
| **Perfil** | Dados próprios + sessões |
| **Integrações** | Google, Telegram Bot, atalhos Trello/Notion |
| **Configurações** | Contactos do site + hub Google (níveis 1–3) |

## Segurança

- Middleware `auth` no painel; apenas `is_admin` acessa o admin
- Headers de segurança (CSP, X-Frame-Options, nosniff, Referrer-Policy)
- Throttle no login (`5/min`) e no formulário público
- Hash de senha, regeneração de sessão no login/logout
- Histórico de tentativas de login e revogação de sessões (`SESSION_DRIVER=database`)
- Senha mínima com letras e números (regra `Password::defaults`)
- Honeypot no contacto público
- Login admin também via Telegram (desafio temporário no bot — sem widget)

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

Documentação completa no painel: **Admin → Configurações → Integração Google** (`/admin/configuracoes#google-integration`).

| Nível | O que ativa | Onde configurar |
|------|-------------|-----------------|
| **1 — Básico** | Botão “Google Agenda” e atalho para criar evento/Meet | Admin → URL da agenda |
| **2 — Operacional** | Agenda embutida no painel de atividades | Admin → Embed |
| **3 — Total** | API cria eventos + Meet **no CRM** | Client ID/Secret + **Ligar conta Google** + Calendar ID + auto-sync |

<details>
<summary>Referência rápida — nível 3</summary>

1. Registe no Cloud Console a URI: `${APP_URL}/admin/google/callback`
2. Cole Client ID/Secret em **Configurações** (ou no `.env`) e salve
3. Clique em **Ligar conta Google** (refresh token fica no CRM)
4. Calendar ID + auto-sync

```env
GOOGLE_CLIENT_ID=xxxxx.apps.googleusercontent.com
GOOGLE_CLIENT_SECRET=GOCSPX-xxxxx
GOOGLE_REDIRECT_URI="${APP_URL}/admin/google/callback"
```

Fuso: `APP_TIMEZONE=America/Sao_Paulo`. Depois: `php artisan config:clear`.
</details>

## Telegram Bot (CRM)

Documentação operacional: **Admin → Integrações → Telegram** (`/admin/integracoes#telegram`).

Campos separados por `|`. Em `set`, use `.` para manter. Em `del`, confirme com `ok`.

```text
/ajuda
/login email | senha          # vincula chat a admin (mensagem apagada)
/contatos  ·  /contato …
/oportunidades  ·  /oportunidade …
/projetos  ·  /projeto …
/tarefas  ·  /tarefa …        # agenda: abertas + concluídas recentes
/atividades  ·  /atividade …  # último campo opcional: concluir
/mensagens  ·  /mensagem …
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

Webhook: `POST {APP_URL}/webhooks/telegram/{TELEGRAM_WEBHOOK_SECRET}`

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
docs/                    # Documentação de produto / consolidação
```

Fluxo típico: **Request → FormRequest → Controller → Model/Service → View**.

## Testes

Suite PHPUnit (SQLite em memória; ~128 testes):

```bash
composer test
# ou
./bin/test
```

Cobertura inclui site público, auth, CRM (contactos, oportunidades, projetos/board, tarefas, atividades, anexos/preview), Telegram bot, Google/settings e segurança de sessões.

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

**Nota sobre `route:cache`:** com `APP_URL` em subpath (ex. `https://dominio/public`), o cache de rotas quebra a home (`GET /` → 405, só `HEAD`). A app **remove esse cache ao arrancar** e após `optimize`/`route:cache`. Prefira document root = `public/` e `APP_URL` sem path; aí `route:cache` é seguro. Logout volta para a home do site.

## Licença

Projeto privado da BURI-TI. Todos os direitos reservados.

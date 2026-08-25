# Microgate Restrito - Agent Instructions

## Quick Commands

```bash
# Install frontend deps
npm install

# Build CSS (production)
npm run build

# Watch CSS (development)
npm run dev
```

## Stack

- PHP 8.x + MySQL (PDO)
- Vanilla JS + Tailwind CSS 3 (build via Node)
- No PHP framework — plain PHP with bootstrap pattern

## Project Structure

### Public Entrypoints (root)
Root PHP files are thin wrappers delegating to `app/views/pages/`:
- `login.php`, `logout.php`, `restricted.php`, `escala.php`
- `visualizar_agenda.php`, `historico.php`, `quilometragem.php`
- `km_report.php`, `gerenciamento_usuarios.php`, `access_logs.php`
- `forgot_password_requests.php`, `page_header.php` (compat)

### Core App
- `app/bootstrap.php` — global init: timezone (America/Sao_Paulo), session (30min inactivity timeout), DB connection, auth helpers
- `app/config/database.php` — PDO connection from `.env` (DB_HOST, DB_NAME, DB_USER, DB_PASS)
- `app/auth/auth_helper.php` — auth functions: `isLoggedIn()`, `isAdmin()`, `isSuperAdmin()`, `hasFuelAccess()`, `requireLogin()`, `requireAdmin()`
- `app/helpers/urls.php` — URL helpers: `route_url()`, `asset_url()`
- `app/views/pages/` — actual page implementations
- `app/actions/` — AJAX/POST endpoints organized by domain (auth, km, schedule, users)

### Auth Levels (is_admin)
- `0` = User (escala.php only)
- `1` = Super Admin (full panel + user management)
- `2` = Manager (panel, no user management)

### Session
- 30 min inactivity timeout (`SESSION_INACTIVITY_TIMEOUT = 1800`)
- Cookie: lifetime=0 (browser close), secure/httponly/samesite=Lax
- Server-side inactivity check in `bootstrap.php` — destroys session, returns 401 for JSON requests

### Database
- `.env` required at root (not committed)
- Schema: `database/001-schema_completo.sql` + migrations in `database/migrations/`
- Tables: users, schedules, holidays, mileage_logs, auth_access_logs, password_reset_requests

### Frontend
- `src/input.css` → `css/output.css` (Tailwind build)
- `js/app-routes.js` — central route map for AJAX endpoints
- `uploads/` — runtime uploads (km photos, fuel receipts), needs write permission

### CSRF
- Tokens on critical POST flows (login, user management, schedule edits, km/fuel saves)

## Development Notes

- No PHP linter/typechecker configured
- No automated tests
- Tailwind content paths exclude `uploads/` (see tailwind.config.js if exists)
- Android scaffold in `android_st/` (not built)
- `downloads/`, `android_st/`, `css/output.csv`, `uploads/` are gitignored
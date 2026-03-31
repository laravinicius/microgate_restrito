# Microgate Restrito

Sistema web em PHP para gestão de escalas técnicas, usuários, auditoria de acesso e controle de quilometragem.

## Tecnologias

- PHP 8.x
- MySQL com PDO
- Tailwind CSS
- JavaScript vanilla
- Lucide Icons

## Funcionalidades

- Painel administrativo com gestão de usuários
- Calendário de escala para técnicos e administradores
- Importação de escala por CSV
- Relatório e registro de quilometragem
- Auditoria de login/logout
- Proteção com sessão, CSRF e variáveis de ambiente

## Estrutura Atual

### Raiz pública

Os arquivos PHP da raiz são entrypoints públicos e mantêm as URLs estáveis do sistema:

- [index.php](/var/www/microgate_restrito/index.php)
- [login.php](/var/www/microgate_restrito/login.php)
- [restricted.php](/var/www/microgate_restrito/restricted.php)
- [escala.php](/var/www/microgate_restrito/escala.php)
- [quilometragem.php](/var/www/microgate_restrito/quilometragem.php)
- [km_report.php](/var/www/microgate_restrito/km_report.php)
- [gerenciamento_usuarios.php](/var/www/microgate_restrito/gerenciamento_usuarios.php)
- [access_logs.php](/var/www/microgate_restrito/access_logs.php)
- [visualizar_agenda.php](/var/www/microgate_restrito/visualizar_agenda.php)
- [logout.php](/var/www/microgate_restrito/logout.php)

### App interno

- `app/bootstrap.php`: bootstrap da aplicação
- `app/config/`: configuração de banco
- `app/auth/`: autenticação e auditoria
- `app/helpers/`: helpers compartilhados, como URLs
- `app/views/pages/`: implementação real das telas
- `app/actions/`: endpoints e ações do sistema
- `app/support/debug/`: utilitários de debug

### Assets

- `css/`: CSS gerado e estilos globais
- `js/`: scripts de frontend e mapa central de rotas
- `img/`: imagens do sistema
- `uploads/`: uploads de evidências

## Fluxo de Organização

- A raiz expõe apenas as entradas públicas
- As telas reais ficam em `app/views/pages`
- As ações HTTP ficam em `app/actions`
- Rotas e assets usam helpers no PHP e `js/app-routes.js` no frontend

## Configuração

1. Configure o arquivo `.env` na raiz do projeto:

```env
DB_HOST=localhost
DB_NAME=nome_do_banco
DB_USER=usuario
DB_PASS=senha
```

2. Garanta permissão de leitura e escrita para `uploads/` quando necessário.
3. Configure o servidor web para servir este diretório como raiz pública.
4. Certifique-se de que as tabelas principais existam no banco, como:

- `users`
- `schedules`
- `holidays`
- `mileage_logs`
- `auth_access_logs`
- `password_reset_requests`

## Observações

- O arquivo `.env` continua na raiz do projeto.
- As URLs públicas antigas foram preservadas de propósito.
- O arquivo [page_header.php](/var/www/microgate_restrito/page_header.php) na raiz existe só como compatibilidade e delega para [components/page_header.php](/var/www/microgate_restrito/components/page_header.php).

Desenvolvido para **Microgate Informática**.

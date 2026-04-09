# Microgate Restrito

Sistema web em PHP para gestão de escalas técnicas, usuários, auditoria de acesso e controle de quilometragem.

## Stack atual

- PHP 8.x
- MySQL (PDO)
- JavaScript vanilla
- Tailwind CSS 3
- Node.js (apenas para build de CSS)

## Principais funcionalidades

- Autenticação com sessão e timeout por inatividade
- Recuperação de acesso com solicitação de reset de senha
- Gestão de usuários (cadastro, edição e controle de status)
- Gestão de escala (visualização, edição e importação)
- Consulta de agenda disponível
- Registro de quilometragem com upload de foto
- Relatório de quilometragem e lançamento manual
- Histórico e auditoria de acessos
- Proteções com token CSRF em fluxos críticos

## Estrutura do projeto

### Entrypoints públicos (raiz)

Arquivos PHP da raiz que mantêm as URLs públicas do sistema:

- index.php
- login.php
- logout.php
- restricted.php
- escala.php
- visualizar_agenda.php
- historico.php
- quilometragem.php
- km_report.php
- gerenciamento_usuarios.php
- access_logs.php

Arquivos PHP de suporte na raiz (compatibilidade/infra):

- forgot_password_requests.php
- page_header.php

### Núcleo da aplicação

- app/bootstrap.php: bootstrap global, sessão e timezone
- app/config/database.php: conexão PDO e leitura de variáveis do .env
- app/auth/: autenticação e auditoria
- app/helpers/: helpers utilitários (incluindo URLs)
- app/views/pages/: implementação real das telas
- app/actions/: endpoints internos (POST/AJAX)
- app/support/debug/: utilitários de debug

### Endpoints internos atuais

- app/actions/auth/login_post.php
- app/actions/auth/forgot_password_request_post.php
- app/actions/schedule/get_schedule.php
- app/actions/schedule/get_available.php
- app/actions/schedule/save_schedule_day.php
- app/actions/schedule/import_schedules.php
- app/actions/km/save_km.php
- app/actions/km/get_km_report.php
- app/actions/km/save_manual_km.php
- app/actions/km/serve_km_photo.php
- app/actions/users/cadastro_usuario_post.php
- app/actions/users/edit_user_post.php

### Frontend e assets

- src/input.css: entrada do Tailwind
- css/output.css: CSS compilado
- js/: scripts da interface e mapa central de rotas
- components/: cabeçalho e componentes compartilhados
- img/: imagens estáticas
- uploads/: evidências (ex.: fotos de KM)

### App Android (estrutura inicial)

- android_st/: esqueleto de app Android com AndroidManifest.xml e MainActivity.kt

## Configuração local

1. Garanta os pré-requisitos:
- PHP 8.x com PDO MySQL
- MySQL
- Node.js 18+ e npm

2. Configure o .env na raiz do projeto:

```env
DB_HOST=localhost
DB_NAME=microgate_db
DB_USER=root
DB_PASS=
```

3. Instale dependências frontend:

```bash
npm install
```

4. Build de estilos:

```bash
npm run build
```

5. Durante desenvolvimento, rode o watch do Tailwind:

```bash
npm run dev
```

6. Garanta permissão de leitura/escrita em uploads/ e configure o servidor web apontando para a raiz do projeto.

## Banco de dados

Tabelas usadas pelo sistema (entre outras):

- users
- schedules
- holidays
- mileage_logs
- auth_access_logs
- password_reset_requests

Observação: a tabela password_reset_requests pode ser criada automaticamente pelo fluxo de solicitação de reset.

## Notas de manutenção

- O scanner do Tailwind está configurado para evitar varredura em uploads/.
- URLs públicas da raiz foram preservadas por compatibilidade.
- page_header.php na raiz delega para components/page_header.php.

Desenvolvido para Microgate Informática.

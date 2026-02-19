# Microgate Restrito - Sistema de Gestão de Escalas

Este é um sistema web desenvolvido para a gestão de escalas de técnicos, permitindo o controle de usuários, visualização de calendários e importação automatizada de dados via arquivos CSV.

## 🚀 Tecnologias Utilizadas

### Backend
- **PHP 8.x**: Linguagem principal para lógica de servidor e processamento de dados.
- **PDO (PHP Data Objects)**: Utilizado para comunicação segura com o banco de dados, prevenindo SQL Injection.
- **BCRYPT**: Criptografia de alta segurança para armazenamento de senhas.

### Frontend
- **Tailwind CSS**: Framework utilitário para um design moderno, responsivo e com suporte a modo escuro (Dark Mode).
- **Vanilla JavaScript (ES6+)**: Lógica de interface, renderização dinâmica de calendários e consumo de APIs internas.
- **Lucide Icons**: Biblioteca de ícones leves e consistentes.
- **Google Fonts (Inter)**: Tipografia focada em legibilidade.

### Banco de Dados
- **MySQL**: Armazenamento de usuários, escalas e feriados.

## 🛠️ Funcionalidades Principais

- **Painel Administrativo**: Gestão completa de usuários (Criação, Edição e Exclusão).
- **Importação de Escala**: Processador de arquivos CSV com lógica de *fuzzy matching* para associar nomes da planilha aos usuários do sistema.
- **Calendário Responsivo**: Visualização de escala *mobile-first* com suporte a abas para múltiplos meses.
- **Segurança**:
    - Proteção contra ataques **CSRF** via tokens de sessão.
    - Proteção contra **XSS** através de sanitização de outputs.
    - Gerenciamento de credenciais sensíveis via variáveis de ambiente (`.env`).
    - Controle de acesso baseado em níveis (Admin vs. Padrão).

## 📋 Estrutura do Projeto

- `/config`: Configurações de conexão com o banco de dados.
- `/css`: Estilos processados pelo Tailwind.
- `/js`: Scripts de comportamento do calendário e componentes.
- `/db`: Modelos de arquivos para importação.
- `restricted.php`: Painel de controle do administrador.
- `import_schedules.php`: Motor de processamento de escalas.
- `get_schedule.php`: API interna que fornece dados para o calendário.

## 🔧 Configuração

1. Clone o repositório.
2. Configure o arquivo `.env` na raiz do projeto com as credenciais do seu banco de dados:
   ```env
   DB_HOST=localhost
   DB_NAME=nome_do_banco
   DB_USER=usuario
   DB_PASS=senha
   ```
3. Certifique-se de que o servidor web tenha permissão de leitura para os arquivos e que o `mod_rewrite` (no caso do Apache) esteja ativo se necessário.
4. Importe a estrutura do banco de dados (tabelas `users`, `schedules` e `holidays`).

---
Desenvolvido para **Microgate Informática**.
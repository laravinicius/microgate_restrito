/**
 * session-guard.js
 *
 * Detecta quando o usuário fechou a aba ou o app e força o logout.
 *
 * Como funciona:
 *   - sessionStorage é limpo automaticamente pelo navegador/WebView quando a
 *     aba é fechada ou o processo do app é encerrado.
 *   - A cada carregamento de página, este script verifica se a chave
 *     'mg_tab_alive' existe no sessionStorage.
 *   - Se não existir (= aba foi fechada e reaberta, ou app foi encerrado),
 *     dispara logout via sendBeacon (fire-and-forget) e redireciona para login.
 *   - Se existir, atualiza o timestamp e segue normalmente.
 *
 * O que NÃO acontece:
 *   - Navegação normal entre páginas do site não apaga o sessionStorage,
 *     então o usuário não é deslogado ao clicar em links.
 *   - O logout só ocorre em carregamentos "frios" (tab/app fechado e reaberto).
 */
(function () {
    'use strict';

    var SESSION_KEY      = 'mg_tab_alive';
    var LOGOUT_ENDPOINT  = '/logout.php';

    // Páginas que não devem disparar o redirect (o usuário já está saindo)
    var isPublicPage = (
        window.location.pathname.indexOf('login.php') !== -1 ||
        window.location.pathname.indexOf('forgot_password') !== -1
    );

    if (isPublicPage) {
        // Nas páginas públicas apenas marca a aba como ativa (caso o usuário
        // acesse o login diretamente sem ter sessão anterior).
        sessionStorage.setItem(SESSION_KEY, String(Date.now()));
        return;
    }

    // ── Página protegida ──────────────────────────────────────────────────────
    var tabAlive = sessionStorage.getItem(SESSION_KEY);

    if (!tabAlive) {
        // sessionStorage vazio = aba/app foi fechado e reaberto.
        // Usa sendBeacon para garantir que o logout chegue ao servidor
        // mesmo que a navegação aconteça antes da resposta.
        if (navigator.sendBeacon) {
            navigator.sendBeacon(LOGOUT_ENDPOINT);
        }
        // Redireciona para login sem adicionar no histórico
        window.location.replace('/login.php');
        return;
    }

    // Aba ainda ativa — atualiza o timestamp
    sessionStorage.setItem(SESSION_KEY, String(Date.now()));
})();
<?php
declare(strict_types=1);

// F-12 FIX: Não carregar Google Analytics em páginas administrativas.
// Dados de navegação do painel admin não devem ser enviados a servidores externos.
if (!empty($_SESSION['is_admin']) && (int)$_SESSION['is_admin'] >= 1) {
    return;
}
?>
<link rel="preconnect" href="https://www.googletagmanager.com">
<link rel="dns-prefetch" href="//www.googletagmanager.com">
<script async src="https://www.googletagmanager.com/gtag/js?id=G-5X34MVSTKP"></script>
<script>
  window.dataLayer = window.dataLayer || [];
  function gtag(){dataLayer.push(arguments);}
  gtag('js', new Date());
  gtag('config', 'G-5X34MVSTKP', { transport_type: 'beacon' });
</script>

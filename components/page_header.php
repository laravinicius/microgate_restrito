<?php
/**
 * components/page_header.php
 * Cabeçalho padrão de página — título, subtítulo, botão voltar e botão sair.
 *
 * Variáveis esperadas (definidas antes do include):
 *   $pageTitle    string  — Título principal (obrigatório)
 *   $pageSubtitle string  — Subtítulo/descrição (opcional, padrão '')
 *   $backUrl      string  — URL do botão "Voltar" (opcional, padrão '' = sem botão voltar)
 *   $backLabel    string  — Texto do botão voltar (opcional, padrão 'Voltar ao Painel')
 */

$pageTitle    = $pageTitle    ?? '';
$pageSubtitle = $pageSubtitle ?? '';
$backUrl      = $backUrl      ?? '';
$backLabel    = $backLabel    ?? 'Voltar ao Painel';
?>
<div class="mb-10 flex flex-col md:flex-row md:items-start md:justify-between gap-6">
    <div>
        <h1 class="text-4xl md:text-5xl font-bold text-white mb-2"><?= htmlspecialchars($pageTitle) ?></h1>
        <?php if ($pageSubtitle): ?>
            <p class="text-gray-400"><?= htmlspecialchars($pageSubtitle) ?></p>
        <?php endif; ?>
    </div>
    <div class="flex flex-col sm:flex-row gap-3 items-start">
        <?php if ($backUrl): ?>
            <a href="<?= htmlspecialchars($backUrl) ?>"
               class="bg-white/10 hover:bg-white/15 border border-white/15 text-white font-semibold py-2 px-4 rounded-lg transition flex items-center gap-2 text-sm">
                <i data-lucide="arrow-left" class="w-4 h-4"></i>
                <?= htmlspecialchars($backLabel) ?>
            </a>
        <?php endif; ?>
        <a href="<?= htmlspecialchars(route_url('logout.php')) ?>"
           class="bg-red-600 hover:bg-red-700 border-2 border-red-500 text-white font-semibold py-2 px-4 rounded-lg transition flex items-center gap-2 text-sm">
            <i data-lucide="log-out" class="w-4 h-4"></i>
            Sair
        </a>
    </div>
</div>

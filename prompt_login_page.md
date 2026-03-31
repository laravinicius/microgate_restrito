Crie uma tela de login em PHP + Tailwind visualmente idêntica a um sistema corporativo premium com estética dark glassmorphism.

Objetivo visual:
Uma tela de autenticação centralizada, elegante, sóbria e moderna, com aparência de sistema interno empresarial. O layout deve transmitir segurança, foco e sofisticação, sem exageros visuais. O visual precisa parecer exatamente o de um painel administrativo escuro com cartão translúcido sobre imagem de fundo.

Estrutura da página:
- A página ocupa 100vh.
- O conteúdo principal fica centralizado vertical e horizontalmente.
- Existe um container principal com largura máxima pequena, pensado para login, aproximadamente 420px.
- Dentro dele há um card/painel com cantos bem arredondados.
- Dentro do painel existe um formulário empilhado verticalmente com espaçamento confortável entre os elementos.
- No topo do formulário há uma área de branding centralizada.
- A ordem dos elementos deve ser:
  1. Logo da empresa
  2. Título “Sistema de Compras”
  3. Eyebrow/legenda pequena em caixa alta com o texto “Entrar”
  4. Campo de usuário
  5. Campo de senha
  6. Mensagem de erro, quando existir
  7. Botão principal “Entrar”

Estilo geral:
- Tema totalmente escuro.
- Fundo da página com imagem fullscreen em cover.
- Sobre a imagem, aplicar uma camada escura translúcida para reduzir contraste e dar profundidade.
- Adicionar ainda uma sobreposição sutil com gradiente/radial light no topo esquerdo para enriquecer o fundo.
- O painel deve usar efeito glassmorphism:
  - fundo escuro translúcido
  - blur forte no backdrop
  - borda fina semitransparente
  - sombra profunda e difusa
- A composição deve ser minimalista, limpa e corporativa.

Paleta:
- Fundo base quase preto: tons entre #0f0f0f e #1f1f1f
- Superfícies: branco com baixa opacidade sobre fundo escuro
- Texto principal: branco
- Texto secundário: branco com opacidade média
- Texto muted: branco com opacidade mais baixa
- Erro: vermelho fechado com texto em vermelho claro
- Não usar cores vibrantes no login, exceto no estado de erro

Tipografia:
- Fonte sans moderna no estilo Inter ou equivalente
- Título principal com peso forte
- Texto pequeno da eyebrow em uppercase com tracking alto
- Hierarquia clara:
  - título da marca em destaque
  - eyebrow discreta
  - labels legíveis
  - placeholders sutis

Branding:
- A logo deve ficar centralizada no topo do card.
- Altura aproximada visual da logo: 56px em desktop e 44px no mobile.
- Abaixo da logo, o título “Sistema de Compras”.
- Abaixo do título, um texto pequeno uppercase “Entrar” com cor mais suave.

Card/painel:
- Largura máxima aproximada de 420px
- Padding externo do painel compacto
- Dentro do painel, outro bloco visual do formulário com:
  - fundo levemente translúcido
  - borda sutil
  - cantos arredondados
  - padding interno generoso
- Bordas arredondadas grandes: algo entre 18px e 24px
- Sombra forte porém suave

Campos:
- Labels acima dos inputs
- Labels com peso médio e cor branca levemente suavizada
- Inputs com:
  - largura total
  - fundo branco translúcido bem sutil
  - borda fina semitransparente
  - texto branco
  - placeholder com opacidade baixa
  - cantos arredondados médios/grandes
  - padding horizontal e vertical confortável
- No foco:
  - aumentar discretamente a opacidade do fundo
  - realçar a borda
  - aplicar ring suave externo
- Inputs:
  - Usuário: placeholder “Informe seu usuário”
  - Senha: placeholder “Informe sua senha”

Botão:
- Botão principal em largura total
- Visual discreto e sofisticado, sem cor chamativa
- Fundo cinza claro translúcido sobre dark theme
- Borda um pouco mais evidente que a dos inputs
- Texto branco com peso semibold
- Cantos arredondados menores que os inputs, porém ainda suaves
- Hover:
  - leve elevação visual
  - leve clareamento do fundo
  - borda mais perceptível
- Disabled:
  - opacidade reduzida
  - sem elevação
  - cursor not-allowed
- Texto do botão:
  - padrão: “Entrar”
  - carregando: “Entrando...”

Mensagem de erro:
- Exibir abaixo dos campos e acima do botão
- Bloco com padding interno
- Fundo vermelho translúcido
- Borda avermelhada sutil
- Texto vermelho claro
- Bordas arredondadas
- Tipografia com peso semibold

Espaçamento:
- Layout vertical com rhythm consistente
- Espaçamento entre campos: médio
- Espaçamento entre branding e formulário: curto
- Espaçamento geral do form: aproximadamente 18px entre blocos
- Card com respiro suficiente para parecer premium

Responsividade:
- Em telas menores que ~720px:
  - reduzir paddings do card e do form
  - manter largura quase total, com margens laterais pequenas
  - botão e inputs continuam com largura total
  - logo diminui levemente
  - bordas arredondadas permanecem, mas um pouco mais compactas
- A tela deve continuar centralizada e elegante no mobile

Comportamento e acabamento:
- Não usar layout genérico de login branco.
- Não usar gradientes coloridos chamativos.
- Não usar ilustrações ou elementos decorativos excessivos.
- O resultado precisa parecer um sistema real de compras corporativas, dark, elegante, translúcido, focado em credibilidade.
- O HTML deve ficar pronto para PHP, com classes Tailwind organizadas e fáceis de manter.
- Usar Tailwind para reproduzir fielmente:
  - fundo com imagem cover
  - overlay escuro
  - glassmorphism
  - blur
  - bordas translúcidas
  - sombras suaves
  - inputs com estados
  - botão com hover/disabled
  - card centralizado em 100vh

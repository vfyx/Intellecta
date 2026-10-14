# Arquitetura do Intellecta

O projeto usa PHP puro, sem framework ou bundler, mantendo as URLs existentes no formato `?page=...`.

## Fluxo da aplicação

1. [`public/index.php`](../public/index.php) recebe a requisição.
2. `src/app/backend.php` inicia a sessão, atende a API e lê o estado JSON com bloqueio de arquivo. `src/app/bootstrap.php` carrega helpers e rotas.
3. [`src/config/routes.php`](../src/config/routes.php) valida a página e aponta para a view da feature.
4. [`src/components/shell/site.php`](../src/components/shell/site.php) renderiza o shell compartilhado.
5. A view da feature recebe o contexto de dados e renderiza somente a coluna central.

## Organização

- `src/app`: inicialização da aplicação.
- `src/components`: shell e componentes reutilizáveis.
- `src/features`: páginas agrupadas por domínio de produto.
- `src/data`: dados demonstrativos separados por domínio.
- `src/config`: configuração e allowlist de rotas.
- `src/utils`: funções utilitárias.
- `assets/css`: entrada de estilos e camadas visuais.
- `assets/js`: controlador de interações que chama a API com CSRF e atualiza a interface após confirmação do servidor.

## Regras de manutenção

- Preserve os nomes das rotas públicas.
- Escape conteúdo dinâmico com `e()`.
- Prefira componentes compartilhados a HTML duplicado.
- Mantenha a identidade visual definida em `assets/css/app.css`.
- O estado persiste no servidor em `storage/state.json`. Autenticação usa sessões PHP privadas e senhas com hash. Não existe banco de dados.
- Consulte o README para as funções locais e as integrações externas não configuradas.

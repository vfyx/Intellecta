# Contribuindo

## Executar localmente

Na raiz do projeto:

```powershell
php -S localhost:8000 -t public router.php
```

Abra `http://localhost:8000/?page=home`.

## Antes de enviar alterações

Valide todos os arquivos PHP:

```powershell
Get-ChildItem -Recurse -Filter *.php | ForEach-Object { php -l $_.FullName }
```

Também confira espaços e erros básicos:

```powershell
git diff --check
```

## Convenções

- Uma feature deve permanecer em sua pasta de domínio.
- Rotas novas precisam ser adicionadas à allowlist e ao mapa `views`.
- Não introduza dependências sem atualizar a documentação.
- Não altere a paleta, tipografia ou raios sem alinhar com o guia visual.

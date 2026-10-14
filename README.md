# Intellecta — backend sem banco de dados

PHP puro com persistência em JSON. Os arquivos CSS originais foram preservados byte a byte.

## Executar

Requer PHP 8.1+ e um navegador atual. Não precisa de Composer, npm ou banco de dados para usar o site.

1. Extraia o ZIP.
2. No Windows, abra **INICIAR.bat**. Ele procura PHP no PATH, no Laragon e no XAMPP.
3. Abra http://localhost:8000 e crie uma conta. Guarde a chave de recuperação exibida no cadastro.

Ou execute na pasta do projeto:

```sh
php -S localhost:8000 -t public router.php
```

Use exatamente esse comando: o router disponibiliza os assets e impede acesso aos arquivos privados.
No Apache/Laragon, prefira configurar **public/** como raiz do site. Uma cópia dos assets está em public/assets. A raiz do projeto também funciona em Apache com AllowOverride habilitado para os .htaccess. Em subpastas, configure um virtual host com a raiz public para manter os caminhos dos assets.

## Recursos implementados

- Cadastro, login, logout, senhas com hash, proteção de rotas, CSRF e limitação de tentativas de acesso.
- Recuperação por chave pessoal, troca de senha, invalidação de outros acessos e exclusão da conta.
- Chave adicional opcional no login; é uma chave estática de segurança, não um autenticador TOTP nem SMS.
- Publicações, imagens PNG/JPEG/GIF/WebP de até 2 MB, prévia do anexo, tópicos e enquetes de 2 a 6 opções.
- Curtidas, comentários consultáveis, salvos, republicações, compartilhamento de links e exclusão de publicações próprias.
- Perfil editável, perfis públicos, seguir, lista de seguidores, avisos de novas publicações, bloquear e desbloquear.
- Denúncias registradas em storage/state.json, na coleção reports, para o responsável pela instalação. Não existe equipe externa de moderação conectada.
- Conversas reais entre contas cadastradas, atualização a cada 5 segundos e limpeza do histórico apenas para a própria conta.
- Notificações reais de interações e acessos, com leitura persistente.
- Busca na página, busca de pessoas/conversas/ajuda, filtros de tópicos e abas do perfil.
- Preferências de movimento, feed compacto, priorização de perfis seguidos, privacidade e status de atividade.
- Animações pontuais via JavaScript, respeitando a redução de movimento do navegador e da conta.

Os perfis iniciais são exemplos e não enviam respostas. Para testar mensagens de verdade, cadastre duas contas em navegadores ou perfis diferentes e abra a conversa em ambos.

## Dependências externas e limites

Google/Microsoft **não autenticam nesta entrega**: exigem configuração e implementação da integração OAuth com credenciais do responsável. Os botões informam a indisponibilidade e oferecem cadastro local; não simulam login.

Não existe envio de e-mail. A opção de novidades por e-mail informa essa dependência e permanece desativada. Recuperação usa a chave pessoal; alertas de acesso aparecem dentro do site.

A Intellia funciona localmente com um roteiro de estudos e um resumo por extração de até três frases. Não é uma IA generativa conectada. Perguntas fora dessas funções recebem uma explicação clara.

A conta de demonstração é um visitante separado. Seus dados persistem no servidor, mas ela não possui senha de retorno: para acesso recorrente, use o cadastro. O projeto utiliza arquivos locais e foi preparado para a feira/protótipo, não para grande volume de usuários.

## Armazenamento

O diretório storage/ é criado automaticamente, precisa ser gravável pelo PHP e contém state.json e sessions/. É protegido no router e por .htaccess. Nunca exponha esse diretório em um servidor que ignore essas regras. Também é possível definir INTELLECTA_STORAGE com um caminho fora da raiz pública.

As gravações usam bloqueio exclusivo e substituição do arquivo completo. Senhas e chaves ficam como hashes. Imagens ficam no JSON em base64. Faça backup de state.json com o servidor parado. A entrega não inclui contas ou dados de teste.

## Validação

Foram executadas 101 verificações de integração HTTP, lint de todos os arquivos PHP, validação de sintaxe JavaScript e testes de navegador em desktop e celular. Os fluxos de cadastro, publicação/enquete, curtida, comentário, salvos, perfil, seguir, mensagens, limpeza de histórico, assistente e preferência de movimento foram exercitados no navegador sem erros JavaScript.

Para repetir a integração, inicie o servidor em uma pasta de armazenamento descartável e use Node.js 18+:

```powershell
$env:INTELLECTA_STORAGE = "$PWD/storage-test"
php -S localhost:8000 -t public router.php
# Em outro terminal:
$env:TEST_URL = "http://localhost:8000"
node tests/integration.mjs
```

A suíte cria e exclui contas de teste e altera o armazenamento indicado. Não a execute sobre dados reais.

## Estrutura

- src/app/backend.php: regras de negócio, sessões, autorização e armazenamento.
- public/index.php: entrada de páginas e API JSON via POST ?api=1.
- assets/js/app.js: interações ligadas ao backend; mantenha public/assets/js/app.js sincronizado ao editar.
- src/features/: páginas e formulários.
- router.php: servidor PHP local e proteção de arquivos.
- assets/css/: folhas de estilo originais, sem alterações.

O navegador precisa de JavaScript para executar as ações. As fontes externas do Google são opcionais; o CSS possui fontes alternativas.

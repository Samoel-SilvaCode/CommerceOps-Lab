# CommerceOps Lab

Laboratório interativo de e-commerce: do catálogo à expedição. Projeto independente de portfólio, sem vínculo com fornecedores ou plataformas comerciais.

## Stack
HTML5, CSS3, Bootstrap 5.3.6 (arquivo local), JavaScript ES6, Fetch API, PHP 8.4, JSON, HTTP, Postman e Vercel Functions (runtime comunitário vercel-php).

## Executar localmente

Requer PHP 8.4 (PHP 8.2+ também deve funcionar, mas os testes foram feitos em 8.4). Na raiz:

```bash
php -S localhost:8000
```

Abra http://localhost:8000. Não use Live Server nem abra index.html diretamente: o backend PHP precisa estar executando.

## Publicar no Vercel

1. Extraia o ZIP. Suba **os arquivos e pastas internos** para um novo repositório GitHub (inclua `vercel.json`, `api/`, `assets/`, `postman/` e `index.html`).
2. No Vercel: Add New → Project → importe esse repositório.
3. Framework Preset: Other. Root Directory: raiz do repositório. Build Command: vazio. Output Directory: padrão (`.`); sem dependências npm. Node.js: 22.x, se solicitado pelo runtime.
4. Recomendado: em Settings → Environment Variables, cadastre `DEMO_SECRET` com um valor aleatório de 64 caracteres hexadecimais. Gere localmente com `php -r 'echo bin2hex(random_bytes(32)), PHP_EOL;'`. Não envie esse valor ao Git. A variável não é obrigatória para brincar localmente: sem ela a assinatura usa uma chave pública de demo, que não oferece proteção real.
5. Deploy. Teste primeiro `/api/index.php?action=health` no domínio gerado. Deve devolver JSON com `ok: true` e runtime PHP. Depois abra a página inicial e simule uma compra. Se aparecer 404, verifique se o Vercel detectou a função `api/index.php`; em caso de erro de compilação consulte os logs da implantação.

O runtime comunitário está configurado como `vercel-php@0.8.0` (PHP 8.4). Ele não é um runtime oficial com suporte do Vercel. Documentação: https://github.com/vercel-community/php e https://vercel.com/docs/functions/runtimes. Deploy real ainda não verificado.

## Demonstração em 60 segundos

1. Abra a visão geral e observe os indicadores (dados iniciais fictícios).
2. Loja & checkout: adicione um Headphone Studio e aplique `DEMO10`. Calcule na API: subtotal R$ 249,90, desconto R$ 24,99, frete simulado R$ 12,90, total R$ 237,81.
3. Crie o pedido. O estoque é reservado pelo PHP.
4. Em Pedidos, simule recusa, aprove o pagamento e reenvie o webhook. A duplicata não altera o estado atual.
5. Expedir pedido → confirmar entrega. O dashboard recalcula os indicadores.
6. Em Integrações, simule timeout de marketplace; observe Eventos & logs.
7. Em API Playground, execute uma requisição e mostre o JSON e o código HTTP.

## API e Postman

Importe `postman/CommerceOps.postman_collection.json`. Ajuste `baseUrl` para a URL do Vercel, sem barra final. Use Collection Runner, na ordem das 17 requisições, começando pela inicialização da sessão. Os scripts de teste capturam o token e os IDs automaticamente. Execute uma iteração por vez. A coleção não compartilha a sessão do navegador.

Rota base: `/api/index.php?action=...`. Ações de sessão enviam `_demoToken` no corpo JSON (ou, opcionalmente, `X-Demo-State` quando o token for pequeno). O playground injeta o token automaticamente. O retorno traz `ok`, `data`, `meta` e, quando pertinente, `state` e `token`. Os valores monetários são inteiros em centavos.

| Método | action | Operação |
|---|---|---|
| GET | health | Verifica runtime |
| GET | bootstrap | Inicializa sessão fictícia |
| POST | state | Consulta snapshot de sessão |
| POST | quote | Valida itens, cupom, frete |
| POST | orders | Cria pedido e reserva estoque |
| PATCH | stock | Atualiza saldo |
| POST | webhook | Aprova/recusa pagamento simulado |
| PATCH | fulfillment | Expedição, entrega ou cancelamento |
| POST | sync | Simula sucesso ou falha de integração |

Códigos HTTP: 200, 201, 400, 401, 404, 405, 409, 413, 422 e 500. A API é orientada a ações HTTP/JSON, não uma implementação REST purista com rotas de recursos.

## Arquitetura e limites

Navegador → Fetch API → PHP serverless → validações/regras de negócio → JSON com snapshot assinado → localStorage do navegador.

Sem banco de dados externo: cada sessão transporta seu próprio estado. Isso facilita demonstrar sem credenciais externas e evita depender de gravação de arquivos no servidor serverless. O token não é criptografado, e não é autenticação de usuário. Utilize exclusivamente dados fictícios. O snapshot pode ser reexecutado/ramificado; não há controle de concorrência entre abas, transações compartilhadas nem idempotência global. A prevenção de duplicatas vale no histórico daquele snapshot. Pagamentos, fretes, marketplaces e rastreio são simulados; não há chamadas comerciais reais. Estoque representa o saldo disponível (reservas ocorrem no pedido). Não há emissão fiscal, autenticação, nem fluxo de estorno. Limite de 60 pedidos por sessão. Em produção seria necessário banco transacional, autenticação/autorização, integração autenticada de webhooks, filas, idempotência persistente e observabilidade de servidor.

## Testes

```bash
python tests/smoke.py
# ou python tests/smoke.py https://seu-dominio.vercel.app
```

Resultado local: 25 requisições HTTP passaram, incluindo rejeição de estoque insuficiente, SKU duplicado acima do saldo, cupom inválido, pagamento recusado, duplicata, transição inválida, cancelamento e devolução de estoque, saldo inválido, erro de integração e token alterado. Sintaxe PHP e JavaScript verificadas. Coleção Postman fornecida, mas não executada no aplicativo Postman neste ambiente. Teste visual automatizado não concluído por bloqueio do navegador de teste ao localhost. Valide a experiência mobile e o deploy antes de compartilhar publicamente.

Bootstrap: licença MIT, aviso preservado no CSS distribuído.

## Versão 2 — Storefront e gestão

A página inicial agora é a loja (`/#store`), com catálogo, categorias, pesquisa, ordenação, detalhes dos produtos e carrinho. A área administrativa está em `/#dashboard`; o laboratório de APIs, em `/#api`. A API PHP e suas regras de negócio permanecem as mesmas. A coleção Postman usa `https://commerceops-lab.vercel.app` como `baseUrl` inicial; altere para localhost se necessário.

### Imagens e referências

As imagens ilustram produtos fictícios, não anúncios ou modelos comerciais específicos. As artes locais em `assets/products` são ilustrações vetoriais próprias. Algumas fotografias de referência são carregadas via Pexels e possuem fallback local em caso de erro:

- [Banner de estação de trabalho](https://www.pexels.com/photo/a-computer-monitor-headphones-and-mouse-on-a-desk-27559555/)
- [Headphones](https://www.pexels.com/photo/black-studio-headphones-on-white-background-30428610/)
- [Teclado](https://www.pexels.com/photo/modern-black-mechanical-keyboard-on-wooden-desk-36203655/)
- [Mouse](https://www.pexels.com/photo/close-up-of-a-modern-white-wireless-mouse-32755767/)
- [Monitor](https://www.pexels.com/photo/a-computer-monitor-on-a-desk-with-a-keyboard-and-mouse-27559482/)

Referências de experiência de compra: Apple Store (apresentação e hierarquia), Magalu (busca, filtros) e Mercado Livre (categorias e navegação de compra). Identidade visual independente, sem associação comercial.

Validação desta versão: 25 requisições HTTP aprovadas localmente; sintaxe JS validada. O ambiente de teste bloqueou o acesso do Chromium à página, portanto a validação visual automatizada e a disponibilidade das fotografias no navegador publicado ainda precisam ser conferidas. O deploy existente não foi alterado.

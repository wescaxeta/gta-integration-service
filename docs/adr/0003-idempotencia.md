# ADR 0003: Idempotência na emissão de GTA

- **Status:** aceita
- **Data:** 2026-10-02

## Contexto

Emitir uma GTA não é uma operação "segura de repetir": duas GTAs para o mesmo lote de
animais são um problema sanitário e fiscal. Mesmo assim, clientes repetem requisições:

- quando recebem **503** porque o cadastro estadual estava fora do ar;
- quando a conexão cai **depois** de o servidor gravar, mas **antes** de a resposta chegar;
- por duplo clique ou retry automático do cliente HTTP.

## Decisão

Seguir o padrão do header **`Idempotency-Key`** (draft IETF *httpapi-idempotency-key-header*):

1. `POST /gtas` exige `Idempotency-Key` (8 a 64 caracteres; um UUID é o ideal).
2. A GTA guarda a chave e um **hash SHA-256 do conteúdo** da requisição
   ([`ChaveIdempotencia`](../../src/Domain/ChaveIdempotencia.php)).
3. Mesma chave + mesmo conteúdo: devolve a GTA original com **200** e o header
   `Idempotent-Replayed: true`, **sem** consultar de novo o sistema externo.
4. Mesma chave + conteúdo diferente: **422**. É erro do cliente reutilizar a chave.
5. **Corrida entre requisições simultâneas:** a garantia final é a constraint
   `UNIQUE (chave_idempotencia)` no PostgreSQL. Se o `INSERT` violar a constraint, o repositório
   lança `ChaveIdempotenciaJaUtilizada` e o caso de uso devolve a GTA gravada pela outra requisição.

## Consequências

- O cliente pode repetir com segurança qualquer `POST /gtas` que falhou ou não teve resposta.
- A verificação prévia (`SELECT` pela chave) evita chamadas desnecessárias ao SOAP. A constraint
  cobre a janela de corrida que o `SELECT` sozinho não cobre.
- As chaves não expiram. Em produção, teríamos uma política de retenção (ex.: 24 h, como a Stripe).

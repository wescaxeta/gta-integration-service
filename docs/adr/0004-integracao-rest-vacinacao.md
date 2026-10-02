# ADR 0004: Integração REST com a API de vacinação (.NET)

- **Status:** aceita
- **Data:** 2026-10-02

## Contexto

Um rebanho só pode ser transportado com as vacinas obrigatórias em dia. Essa informação pertence
a outro sistema, a [vacinacao-api](https://github.com/wescaxeta/vacinacao-api), feito em .NET por
outra "equipe" e com outro ciclo de deploy. Ela expõe `GET /propriedades/{codigo}/aptidao?especie=`.

## Decisão

1. O domínio ganha uma segunda **porta**, [`AptidaoSanitaria`](../../src/Domain/AptidaoSanitaria.php),
   que devolve um `ResultadoAptidao` (apta + pendências). O caso de uso não sabe que existe HTTP, JSON ou .NET.
2. O adaptador [`RestAptidaoSanitaria`](../../src/Integration/Rest/RestAptidaoSanitaria.php) usa **Guzzle**
   e segue as mesmas regras do adaptador SOAP ([ADR 0002](0002-camada-anticorrupcao-soap.md)):
   - `connect_timeout` e `timeout` explícitos;
   - **nova tentativa** só em falhas transitórias: erro de conexão/timeout (`ConnectException`) e respostas **5xx**;
   - respostas **4xx** não são repetidas: a requisição está errada e repetir não muda nada;
   - o JSON é **validado** (`apta` booleano, `pendencias` lista) antes de virar objeto de domínio.
3. **Ordem das validações:** primeiro as regras do cadastro (SOAP), depois a vacinação (REST). Se a
   origem já está bloqueada ou sem saldo, não há por que chamar o segundo sistema.
4. **Falha fechada:** se a API de vacinação estiver fora do ar, a GTA **não** é emitida (503 com
   `Retry-After`). Emitir sem confirmar a vacinação seria um risco sanitário; o cliente reenvia
   depois com a mesma `Idempotency-Key`.
5. O `compose.yaml` sobe a vacinacao-api **a partir do repositório dela no GitHub**, como um sistema
   de outra equipe seria consumido: pelo contrato público, sem compartilhar código.

## Consequências

- As duas integrações têm o mesmo comportamento sob falha, o que facilita operar e explicar o sistema.
- A disponibilidade da emissão passa a depender de dois sistemas externos. O próximo passo natural é
  um *circuit breaker*, para não esperar todas as tentativas quando um deles está fora do ar há minutos.
- Os testes do adaptador usam o `MockHandler` do Guzzle (sem rede); o teste de ponta a ponta no CI
  roda os três sistemas de verdade com Docker Compose.

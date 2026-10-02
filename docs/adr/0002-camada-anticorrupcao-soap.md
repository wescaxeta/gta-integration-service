# ADR 0002: Camada anticorrupção para o WebService SOAP

- **Status:** aceita
- **Data:** 2026-10-02

## Contexto

O cadastro de propriedades e rebanhos fica em um sistema estadual que só expõe um
WebService SOAP. Sistemas assim costumam ter limitações conhecidas:

- ficam lentos ou fora do ar em horários de pico;
- mudam o contrato sem aviso (um novo valor de enum, um campo que vira opcional);
- usam vocabulário próprio (`situacao = "ATIVA"`), diferente do vocabulário do nosso domínio.

## Decisão

1. O domínio define uma **porta**, a interface [`CadastroAgropecuario`](../../src/Domain/CadastroAgropecuario.php),
   com os métodos de que precisa, nos termos do próprio domínio.
2. [`SoapCadastroAgropecuario`](../../src/Integration/Soap/SoapCadastroAgropecuario.php) é o
   **adaptador**: chama o SOAP, valida a resposta campo a campo
   ([`RespostaSoap`](../../src/Integration/Soap/RespostaSoap.php)) e devolve objetos de domínio.
3. **Falhas transitórias** (fault `HTTP` = timeout/conexão; fault `Server` = erro interno) são
   repetidas com [backoff exponencial](../../src/Integration/Retry/RetryPolicy.php) (200 ms, 400 ms...).
   Faults `Client` (requisição inválida) **não** são repetidos.
4. Esgotadas as tentativas, o adaptador lança `IntegracaoIndisponivel`, que a API devolve como
   **503 com `Retry-After`**. O cliente sabe que pode tentar de novo e, graças à
   [idempotência](0003-idempotencia.md), sem risco de duplicar a GTA.
5. Timeouts explícitos: `connection_timeout` para o connect e `stream_context` para a leitura.
   Sem eles, uma chamada travada prende o worker PHP até o `default_socket_timeout` (60 s).

## Consequências

- Trocar SOAP por REST no futuro afeta só o adaptador.
- Uma resposta fora do contrato vira erro claro ("situação de propriedade desconhecida"),
  e não um `TypeError` no meio da regra de negócio.
- Os testes exercitam o SOAP real (WSDL + XML) sem rede, com um `SoapClient` que entrega a
  requisição a um `SoapServer` no mesmo processo
  ([`SoapClientEmProcesso`](../../tests/Double/SoapClientEmProcesso.php)).
- Ainda não há *circuit breaker*: com o serviço fora do ar por muito tempo, cada requisição
  ainda faz todas as tentativas. Está no roadmap.

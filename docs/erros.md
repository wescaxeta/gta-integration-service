# Catálogo de erros

Todas as respostas de erro seguem o formato **Problem Details**
([RFC 9457](https://www.rfc-editor.org/rfc/rfc9457)), com `Content-Type: application/problem+json`.
O campo `type` aponta para a seção correspondente deste documento.

```json
{
  "type": "/docs/erros#regra-emissao-violada",
  "title": "Unprocessable Entity",
  "status": 422,
  "detail": "Saldo insuficiente de bovino na origem: disponível 500, solicitado 9000."
}
```

## requisicao-invalida

**400**: falta o header `Idempotency-Key` ou o corpo não é JSON.
**422**: um ou mais campos têm formato inválido. O campo `errors` traz as mensagens por campo:

```json
{ "errors": { "origem": ["Use a UF seguida de 6 dígitos (ex.: GO000123)."] } }
```

## dado-invalido

**422**: o dado tem formato válido, mas viola uma invariante (ex.: origem igual ao destino).

## regra-emissao-violada

**422**: o cadastro agropecuário impede a emissão:

- propriedade de origem ou de destino não encontrada;
- origem com situação diferente de `ATIVA`;
- destino `INATIVA`;
- saldo de animais da espécie insuficiente na origem.

## conflito-idempotencia

**422**: a `Idempotency-Key` já foi usada com outro conteúdo. Gere uma nova chave.

## gta-nao-encontrada

**404**: não existe GTA com o id informado.

## transicao-invalida

**409**: a GTA já está cancelada ou já venceu.

## integracao-indisponivel

**503**: o cadastro agropecuário não respondeu após as novas tentativas, ou respondeu fora do
contrato. Respeite o header `Retry-After` e reenvie com a **mesma** `Idempotency-Key`.

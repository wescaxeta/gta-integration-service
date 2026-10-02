#!/bin/sh
# Teste de fumaça de ponta a ponta: API -> WebService SOAP -> PostgreSQL.
# Uso: sh tests/smoke.sh [url-base]   (padrão: http://localhost:8080)
set -eu

BASE="${1:-http://localhost:8080}"
CHAVE="smoke-$(date +%s)"
CORPO='{"origem":"GO000001","destino":"GO000002","especie":"bovino","quantidade":5,"finalidade":"abate"}'
FALHAS=0

verificar() {
    descricao="$1"; esperado="$2"; obtido="$3"
    if [ "$esperado" = "$obtido" ]; then
        echo "  ok   $descricao ($obtido)"
    else
        echo "  FALHOU $descricao: esperado $esperado, obtido $obtido"
        FALHAS=$((FALHAS + 1))
    fi
}

status() {
    curl -s -o /dev/null -w '%{http_code}' "$@"
}

echo "Smoke test em $BASE"

verificar "health check" 200 "$(status "$BASE/health")"

LOCAL=$(curl -s -D - -o /dev/null -X POST "$BASE/gtas" \
    -H 'Content-Type: application/json' -H "Idempotency-Key: $CHAVE" -d "$CORPO" \
    | tr -d '\r' | sed -n 's/^[Ll]ocation: //p')
verificar "emissão retorna Location" "yes" "$([ -n "$LOCAL" ] && echo yes || echo no)"

verificar "reenvio idempotente" 200 "$(status -X POST "$BASE/gtas" \
    -H 'Content-Type: application/json' -H "Idempotency-Key: $CHAVE" -d "$CORPO")"

verificar "consulta da GTA emitida" 200 "$(status "$BASE$LOCAL")"

verificar "cancelamento" 200 "$(status -X POST "$BASE$LOCAL/cancelamento")"

verificar "segundo cancelamento recusado" 409 "$(status -X POST "$BASE$LOCAL/cancelamento")"

verificar "SOAP indisponível vira 503" 503 "$(status -X POST "$BASE/gtas" \
    -H 'Content-Type: application/json' -H "Idempotency-Key: $CHAVE-soap" \
    -d '{"origem":"GO999999","destino":"GO000002","especie":"bovino","quantidade":5,"finalidade":"abate"}')"

if [ "$FALHAS" -gt 0 ]; then
    echo "$FALHAS verificação(ões) falharam."
    exit 1
fi

echo "Tudo certo."

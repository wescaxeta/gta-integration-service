-- Schema da GTA (Guia de Trânsito Animal).
-- Executado automaticamente pelo container do PostgreSQL na primeira inicialização.

CREATE TABLE IF NOT EXISTS gta (
    id                 UUID         PRIMARY KEY,
    origem             CHAR(8)      NOT NULL,
    destino            CHAR(8)      NOT NULL,
    especie            VARCHAR(20)  NOT NULL,
    quantidade         INTEGER      NOT NULL,
    finalidade         VARCHAR(20)  NOT NULL,
    status             VARCHAR(20)  NOT NULL,
    emitida_em         TIMESTAMPTZ  NOT NULL,
    valida_ate         TIMESTAMPTZ  NOT NULL,
    cancelada_em       TIMESTAMPTZ,
    chave_idempotencia VARCHAR(64)  NOT NULL,
    hash_requisicao    CHAR(64)     NOT NULL,

    CONSTRAINT uq_gta_chave_idempotencia UNIQUE (chave_idempotencia),
    CONSTRAINT ck_gta_quantidade_positiva CHECK (quantidade > 0),
    CONSTRAINT ck_gta_origem_destino CHECK (origem <> destino),
    CONSTRAINT ck_gta_status CHECK (status IN ('emitida', 'cancelada')),
    CONSTRAINT ck_gta_cancelamento CHECK ((status = 'cancelada') = (cancelada_em IS NOT NULL)),
    CONSTRAINT ck_gta_validade CHECK (valida_ate > emitida_em)
);

-- Consulta mais comum: GTAs emitidas por uma propriedade, das mais recentes para as mais antigas.
CREATE INDEX IF NOT EXISTS ix_gta_origem_emitida_em ON gta (origem, emitida_em DESC);
CREATE INDEX IF NOT EXISTS ix_gta_destino_emitida_em ON gta (destino, emitida_em DESC);

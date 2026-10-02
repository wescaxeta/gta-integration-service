# ADR 0001: Mezzio (Laminas) em vez de Laravel

- **Status:** aceita
- **Data:** 2026-10-02

## Contexto

O serviço é uma API pequena, cujo valor está nas regras de negócio e na integração com
um sistema externo. Não há telas, filas de e-mail ou painel administrativo.

O ecossistema Laminas (antigo Zend Framework) é muito usado em sistemas de governo e em
aplicações corporativas de longa duração. Mezzio é o microframework desse ecossistema,
construído sobre os padrões PSR-7 (mensagens HTTP), PSR-15 (middlewares) e PSR-11 (container).

## Decisão

Usar **Mezzio** com componentes Laminas (`laminas-inputfilter`, `laminas-servicemanager`,
`mezzio-problem-details`).

## Consequências

- **Positivas**
  - O código de aplicação depende de interfaces PSR, não do framework. Handlers são classes
    `RequestHandlerInterface` comuns, fáceis de testar.
  - O pipeline de middlewares é explícito em [`config/pipeline.php`](../../config/pipeline.php);
    não há "mágica" de framework para entender.
  - Conhecimento transferível para projetos Laminas MVC legados.
- **Negativas**
  - Mais configuração manual do que no Laravel: não há ORM, migrations ou filas prontas.
  - Comunidade menor. Para um produto com muitas telas e CRUDs, Laravel seria mais produtivo.

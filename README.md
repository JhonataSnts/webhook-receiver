# HookRelay

HookRelay é um webhook receiver idempotente construído com Laravel. A proposta é receber webhooks externos com validação de assinatura, proteção contra replay attack, persistência de eventos, processamento assíncrono com retry e histórico auditável.

O objetivo do projeto é simular um serviço real de integração, focado em segurança, rastreabilidade e operação. Em vez de tratar webhook como apenas uma rota `POST`, o sistema registra o evento como um fato externo que pode ser validado, deduplicado, processado, auditado e reprocessado.

## Problema

Webhooks parecem simples em tutoriais, mas em produção eles costumam falhar de formas importantes:

- o mesmo evento pode chegar mais de uma vez;
- uma requisição pode ser forjada;
- um payload antigo pode ser reenviado fora da janela esperada;
- o sistema consumidor pode estar fora do ar;
- o processamento pode falhar temporariamente;
- sem histórico, fica difícil auditar o que aconteceu;
- sem replay, a recuperação depende do provedor reenviar o evento.

HookRelay existe para explorar essas decisões de engenharia em um projeto pequeno, mas com preocupações reais.

## Funcionalidades

- Cadastro de fontes de webhook.
- Endpoint único por fonte.
- Secret de assinatura por fonte.
- Validação HMAC SHA-256.
- Proteção contra replay attack usando timestamp.
- Idempotência por `X-HookRelay-Idempotency-Key` ou hash do payload.
- Persistência de payload, headers, IP, user-agent e status.
- Processamento assíncrono com Laravel Queue.
- Registro de tentativas de entrega.
- Retry com backoff auditável.
- Diferenciação entre falha temporária (`retrying`) e falha final (`failed`).
- Replay manual de eventos.
- Histórico visual de eventos.
- Filtro de eventos por status e por fonte.
- Testes automatizados para os fluxos principais.

## Stack

- PHP 8.2+
- Laravel 12
- SQLite no ambiente local
- Laravel Queue com driver `database`
- Blade
- PHPUnit

## Fluxo

```text
Provider externo
    |
    v
POST /webhooks/{sourceUuid}
    |
    v
WebhookReceiverController
    |
    |-- valida fonte ativa
    |-- valida timestamp
    |-- valida assinatura HMAC
    |-- verifica idempotência
    |
    v
WebhookEvent
    |
    v
ProcessWebhookEvent
    |
    v
WebhookDeliveryAttempt
    |
    v
Histórico / Replay / Retry
```

## Modelo de Dados

### `webhook_sources`

Representa uma origem externa autorizada a enviar webhooks.

Campos principais:

- `uuid`
- `name`
- `slug`
- `signing_secret`
- `target_url`
- `is_active`

### `webhook_events`

Representa cada evento recebido.

Campos principais:

- `webhook_source_id`
- `uuid`
- `idempotency_key`
- `payload_hash`
- `signature_header`
- `timestamp_header`
- `status`
- `rejection_reason`
- `payload`
- `headers`
- `ip_address`
- `user_agent`
- `received_at`
- `processed_at`

### `webhook_delivery_attempts`

Representa cada tentativa de processamento/entrega do evento.

Campos principais:

- `webhook_event_id`
- `attempt_number`
- `status`
- `response_status`
- `response_body`
- `error_message`
- `attempted_at`
- `next_retry_at`

## Status dos Eventos

| Status | Significado |
| --- | --- |
| `received` | Evento aceito e persistido. |
| `queued` | Evento reenfileirado manualmente via replay. |
| `processing` | Evento em processamento. |
| `retrying` | A tentativa falhou, mas ainda existe retry pendente. |
| `processed` | Evento processado com sucesso ou sem destino configurado. |
| `failed` | Evento falhou após esgotar as tentativas. |
| `rejected` | Evento rejeitado por regra de segurança. |

## Headers do Webhook

Para enviar um webhook válido, a requisição deve incluir:

| Header | Descrição |
| --- | --- |
| `X-HookRelay-Timestamp` | Unix timestamp usado na assinatura e na proteção contra replay. |
| `X-HookRelay-Signature` | Assinatura HMAC no formato `sha256={hash}`. |
| `X-HookRelay-Idempotency-Key` | Chave opcional para evitar processamento duplicado. |

## Assinatura

A assinatura é calculada usando:

```text
timestamp.payload_bruto
```

Com HMAC SHA-256 e o `signing_secret` da fonte.

Formato final:

```text
sha256={hash_hmac_sha256}
```

## Como Rodar Localmente

Instale as dependências:

```bash
composer install
npm install
```

Crie o `.env`:

```bash
cp .env.example .env
php artisan key:generate
```

Crie o banco SQLite, se ainda não existir:

```bash
touch database/database.sqlite
```

Rode migrations e seed:

```bash
php artisan migrate --seed
```

Inicie o servidor:

```bash
php artisan serve
```

Em outro terminal, rode o worker da fila:

```bash
php artisan queue:work
```

Acesse:

```text
http://127.0.0.1:8000/events
```

## Fonte Demo

O seed cria uma fonte de demonstração:

```text
UUID: 11111111-1111-4111-8111-111111111111
Secret: hookrelay-demo-secret
Endpoint: /webhooks/11111111-1111-4111-8111-111111111111
```

## Exemplo de Requisição

### Usando o comando demo

Para praticar o fluxo sem depender de Postman, curl ou outro sistema externo, use o comando Artisan:

```bash
php artisan hookrelay:send-demo accepted --url=http://localhost/webhook-receiver/public
```

Esse comando simula um provedor externo enviando um webhook assinado para a fonte demo.

Depois de rodar, abra:

```text
http://localhost/webhook-receiver/public/events
```

Você deve ver um novo evento no histórico.

Para simular um evento duplicado:

```bash
php artisan hookrelay:send-demo duplicate --url=http://localhost/webhook-receiver/public
```

Esse cenário envia duas requisições com a mesma `X-HookRelay-Idempotency-Key`. A primeira deve ser aceita e a segunda deve retornar `duplicate`.

Para simular assinatura inválida:

```bash
php artisan hookrelay:send-demo rejected --url=http://localhost/webhook-receiver/public
```

Esse cenário envia uma assinatura propositalmente incorreta. O evento deve aparecer como `rejected` no histórico.

Se estiver usando `php artisan serve`, a URL normalmente será:

```bash
php artisan hookrelay:send-demo accepted --url=http://127.0.0.1:8000
```

### Usando PHP/cURL

Exemplo usando PHP para gerar assinatura e enviar o webhook:

```php
<?php

$payload = json_encode([
    'event' => 'invoice.paid',
    'id' => 'evt_123',
    'amount' => 19990,
]);

$timestamp = time();
$secret = 'hookrelay-demo-secret';
$signature = 'sha256='.hash_hmac('sha256', $timestamp.'.'.$payload, $secret);

$ch = curl_init('http://127.0.0.1:8000/webhooks/11111111-1111-4111-8111-111111111111');

curl_setopt_array($ch, [
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => $payload,
    CURLOPT_HTTPHEADER => [
        'Content-Type: application/json',
        'X-HookRelay-Timestamp: '.$timestamp,
        'X-HookRelay-Signature: '.$signature,
        'X-HookRelay-Idempotency-Key: evt_123',
    ],
    CURLOPT_RETURNTRANSFER => true,
]);

echo curl_exec($ch);
```

Resposta esperada:

```json
{
    "status": "accepted",
    "event_id": "..."
}
```

Se o mesmo evento for reenviado com a mesma idempotency key:

```json
{
    "status": "duplicate",
    "event_id": "..."
}
```

Se a assinatura for inválida:

```json
{
    "status": "rejected",
    "reason": "invalid_signature",
    "event_id": "..."
}
```

## Telas

| Rota | Descrição |
| --- | --- |
| `/events` | Histórico de eventos recebidos. |
| `/events/{uuid}` | Detalhe do evento, payload, headers e tentativas. |
| `/sources` | Lista de fontes cadastradas. |
| `/sources/create` | Cadastro de nova fonte. |
| `/sources/{uuid}` | Detalhe da fonte, endpoint, secret e eventos recentes. |

## Documentação OpenAPI

O contrato HTTP atual esta documentado em:

```text
docs/openapi.yaml
```

O arquivo descreve o endpoint publico de recebimento de webhooks, as respostas `accepted`, `duplicate` e `rejected`, os headers esperados e as rotas operacionais de eventos e fontes.

## Replay Manual

Eventos que não foram rejeitados por segurança podem ser reprocessados manualmente pela tela de detalhe.

O replay não cria um novo `WebhookEvent`. Ele cria uma nova linha em `webhook_delivery_attempts`, muda o evento para `queued` e dispara o job de processamento novamente.

Eventos com status `rejected` não podem ser reprocessados, pois foram recusados por regra de segurança, como assinatura inválida ou timestamp ausente/antigo.

## Retry e Backoff

O processamento usa Laravel Queue com até 3 tentativas.

Backoff atual:

```text
1 tentativa falhou -> próximo retry em 60 segundos
2 tentativa falhou -> próximo retry em 300 segundos
3 tentativa falhou -> falha final
```

Enquanto ainda há retry pendente, o evento fica com status `retrying`. Quando as tentativas acabam, o evento fica com status `failed`.

## Testes

Rode:

```bash
php artisan test
```

Os testes cobrem:

- recebimento de webhook assinado;
- rejeição por assinatura inválida;
- idempotência;
- replay manual;
- bloqueio de replay para eventos rejeitados;
- retry temporário;
- falha final;
- gestão de fontes;
- filtro de eventos por fonte.

## Roadmap

- [x] Receber webhooks assinados.
- [x] Persistir payload, headers e metadados.
- [x] Evitar duplicidade com idempotency key/hash.
- [x] Expor histórico visual.
- [x] Adicionar replay manual.
- [x] Melhorar retry/backoff auditável.
- [x] Adicionar gestão de fontes.
- [x] Adicionar documentação OpenAPI.
- [ ] Adicionar Docker Compose.
- [ ] Adicionar autenticação para o painel.
- [ ] Adicionar rotação/regeneração de signing secret.
- [ ] Adicionar timeline visual do evento.
- [ ] Adicionar métricas operacionais.

## Decisões Técnicas

- O endpoint público usa `uuid` da fonte, não `id` incremental.
- A assinatura usa o payload bruto para evitar divergência após parse JSON.
- Eventos rejeitados também são persistidos para auditoria.
- Replay cria nova tentativa, não novo evento.
- O histórico prioriza operabilidade e debug, não apenas apresentação visual.
- O projeto usa SQLite localmente para reduzir barreira de execução.

## Licença

Projeto de estudo. Ajuste a licença conforme a estratégia do repositório antes de publicar.

# Testes de Segurança do Client Portal - Fase 1

**Data:** 2025-12-07  
**Versão:** 2.4.1  
**Escopo:** Validação das melhorias de segurança da Fase 1

---

## Índice

1. [Resumo Executivo](#resumo-executivo)
2. [Ambiente de Testes](#ambiente-de-testes)
3. [Testes de Rate Limiting](#testes-de-rate-limiting)
4. [Testes de Validação de Token](#testes-de-validação-de-token)
5. [Testes de Validação de IP](#testes-de-validação-de-ip)
6. [Testes de Logging](#testes-de-logging)
7. [Testes de Integração](#testes-de-integração)
8. [Checklist de Validação](#checklist-de-validação)
9. [Relatório de Resultados](#relatório-de-resultados)

---

## Resumo Executivo

Este documento descreve os testes de segurança necessários para validar as melhorias implementadas na **Fase 1 de Segurança** do Client Portal. 

### Melhorias Testadas

1. ✅ Remoção de `session_start()` deprecated
2. ✅ Rate limiting (5 tentativas/hora por IP)
3. ✅ Validação de IP com suporte a IPv6 e proxies
4. ✅ Logging de tentativas inválidas

### Objetivos dos Testes

- Validar que rate limiting previne brute force
- Confirmar que tokens single-use não podem ser reutilizados
- Verificar suporte a IPv4, IPv6 e proxies
- Validar logging completo de tentativas
- Garantir ausência de conflitos de sessão
- Medir impacto em performance

---

## Ambiente de Testes

### Requisitos

**Servidor:**
- WordPress 6.4+
- PHP 8.0+
- MySQL 8.0+ ou MariaDB 10.6+
- Redis ou Memcached (opcional, para object cache)

**Ferramentas:**
- cURL (linha de comando)
- Postman ou Insomnia (REST client)
- Browser DevTools (Network tab)
- WP-CLI (para consultas ao banco)

**Configurações:**
```php
// wp-config.php - Ativar debug para testes
define( 'WP_DEBUG', true );
define( 'WP_DEBUG_LOG', true );
define( 'WP_DEBUG_DISPLAY', false );
```

### Preparação

1. **Criar cliente de teste:**
```bash
wp post create --post_type=dps_cliente \
  --post_title="Cliente Teste Segurança" \
  --meta_input='{"client_phone":"11999999999","client_email":"teste@security.test"}'
```

2. **Gerar token de teste:**
```php
$token_manager = DPS_Portal_Token_Manager::get_instance();
$token = $token_manager->generate_token( $client_id, 'login', 30 );
echo "Token: " . $token . "\n";
```

3. **Configurar headers de proxy (opcional):**
```apache
# .htaccess ou nginx.conf
# Simular Cloudflare
SetEnv HTTP_CF_CONNECTING_IP 203.0.113.42
SetEnv HTTP_X_FORWARDED_FOR 203.0.113.42, 198.51.100.23
```

---

## Testes de Rate Limiting

### Teste 1.1: Limite de 5 Tentativas

**Objetivo:** Validar que após 5 tentativas inválidas em 1 hora, o IP é bloqueado.

**Procedimento:**
```bash
# 1. Gerar token inválido
INVALID_TOKEN="00000000000000000000000000000000000000000000000000000000000000000"

# 2. Fazer 6 requisições seguidas
for i in {1..6}; do
  echo "Tentativa $i:"
  curl -s -o /dev/null -w "%{http_code}\n" \
    "https://seu-site.com/portal/?dps_token=$INVALID_TOKEN"
  sleep 1
done
```

**Resultado Esperado:**
- Tentativas 1-5: Retornam mensagem de "token inválido"
- Tentativa 6: Retorna mensagem de "muitas tentativas" ou redireciona
- Transient `dps_token_attempts_[hash]` = 6

**Validação:**
```bash
# Verificar transient via WP-CLI
wp transient get "dps_token_attempts_$(echo -n '127.0.0.1' | md5sum | cut -d' ' -f1)"
# Deve retornar 6
```

---

### Teste 1.2: Reset Após 1 Hora

**Objetivo:** Confirmar que contador é resetado após 1 hora.

**Procedimento:**
```php
// 1. Forçar expiração do transient
$ip = '127.0.0.1';
$key = 'dps_token_attempts_' . md5( $ip );
delete_transient( $key );

// 2. Fazer nova tentativa
// Deve permitir novamente
```

**Resultado Esperado:**
- Nova tentativa é permitida
- Contador reinicia do zero

---

### Teste 1.3: Rate Limit por IP (Diferentes IPs)

**Objetivo:** Validar que rate limit é por IP, não global.

**Procedimento:**
```bash
# Terminal 1: Simular IP 1
export HTTP_CF_CONNECTING_IP="203.0.113.1"
# Fazer 5 tentativas

# Terminal 2: Simular IP 2
export HTTP_CF_CONNECTING_IP="203.0.113.2"
# Fazer 5 tentativas
```

**Resultado Esperado:**
- Cada IP tem seu próprio contador
- IP1 bloqueado não afeta IP2

---

### Teste 1.4: Token Válido Reseta Rate Limit

**Objetivo:** Confirmar que uso de token válido limpa o contador.

**Procedimento:**
```php
// 1. Fazer 3 tentativas inválidas
// 2. Usar token válido
// 3. Fazer nova tentativa inválida
// Deve permitir (contador foi resetado)
```

**Resultado Esperado:**
- Após token válido, contador = 0
- Nova tentativa inválida inicia nova contagem

---

## Testes de Validação de Token

### Teste 2.1: Single-Use Token

**Objetivo:** Validar que token só funciona uma vez.

**Procedimento:**
```bash
# 1. Gerar token
TOKEN="..."

# 2. Usar token (primeira vez)
curl "https://seu-site.com/portal/?dps_token=$TOKEN"
# Deve autenticar e marcar used_at

# 3. Usar mesmo token (segunda vez)
curl "https://seu-site.com/portal/?dps_token=$TOKEN"
# Deve rejeitar
```

**Resultado Esperado:**
- Primeira tentativa: Sucesso, `used_at` preenchido
- Segunda tentativa: Falha, "token já usado"

**Validação SQL:**
```sql
SELECT id, used_at, revoked_at 
FROM wp_dps_portal_tokens 
WHERE token_hash = ...
-- used_at deve estar preenchido
```

---

### Teste 2.2: Token Expirado

**Objetivo:** Validar que tokens expirados são rejeitados.

**Procedimento:**
```php
// 1. Gerar token com expiração curta (1 minuto)
$token = $token_manager->generate_token( $client_id, 'login', 1 );

// 2. Aguardar 2 minutos
sleep( 120 );

// 3. Tentar usar token
// Deve falhar
```

**Resultado Esperado:**
- Token expirado é rejeitado
- Mensagem: "Link expirado. Solicite novo acesso."

---

### Teste 2.3: Cache Negativo

**Objetivo:** Validar que tokens inválidos são cached por 5 minutos.

**Procedimento:**
```bash
# 1. Tentar token inválido (primeira vez)
time curl "https://seu-site.com/portal/?dps_token=INVALID"
# Deve fazer query ao banco (mais lento)

# 2. Tentar mesmo token inválido (segunda vez)
time curl "https://seu-site.com/portal/?dps_token=INVALID"
# Deve usar cache (mais rápido, sem query)
```

**Resultado Esperado:**
- Segunda tentativa é ~90% mais rápida
- Transient `dps_invalid_token_[hash]` existe

---

## Testes de Validação de IP

### Teste 3.1: IPv4 Padrão

**Objetivo:** Validar detecção de IPv4 via REMOTE_ADDR.

**Procedimento:**
```php
$_SERVER['REMOTE_ADDR'] = '192.168.1.100';
$ip = $token_manager->get_client_ip_with_proxy_support();
assert( $ip === '192.168.1.100' );
```

**Resultado Esperado:**
- IP detectado corretamente

---

### Teste 3.2: IPv6

**Objetivo:** Validar suporte a IPv6.

**Procedimento:**
```php
$_SERVER['REMOTE_ADDR'] = '2001:0db8:85a3:0000:0000:8a2e:0370:7334';
$ip = $token_manager->get_client_ip_with_proxy_support();
assert( filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6 ) );
```

**Resultado Esperado:**
- IPv6 detectado e validado

---

### Teste 3.3: Cloudflare Proxy

**Objetivo:** Validar detecção de IP real via CF-Connecting-IP.

**Procedimento:**
```php
$_SERVER['HTTP_CF_CONNECTING_IP'] = '203.0.113.42';
$_SERVER['REMOTE_ADDR'] = '104.16.0.1'; // IP do Cloudflare
$ip = $token_manager->get_client_ip_with_proxy_support();
assert( $ip === '203.0.113.42' ); // IP real, não do proxy
```

**Resultado Esperado:**
- IP do cliente é detectado, não do Cloudflare

---

### Teste 3.4: X-Forwarded-For com Múltiplos IPs

**Objetivo:** Validar parse correto de lista de IPs.

**Procedimento:**
```php
$_SERVER['HTTP_X_FORWARDED_FOR'] = '203.0.113.42, 198.51.100.23, 192.0.2.1';
$ip = $token_manager->get_client_ip_with_proxy_support();
assert( $ip === '203.0.113.42' ); // Primeiro IP (cliente real)
```

**Resultado Esperado:**
- Extrai primeiro IP da lista corretamente

---

### Teste 3.5: IP Inválido/Privado

**Objetivo:** Validar que IPs privados não são aceitos.

**Procedimento:**
```php
$_SERVER['REMOTE_ADDR'] = '192.168.1.1'; // IP privado
$ip = $token_manager->get_client_ip_with_proxy_support();
// Deve retornar string vazia ou pular para próximo header
```

**Resultado Esperado:**
- IPs privados são ignorados quando possível
- Fallback para REMOTE_ADDR se necessário

---

## Testes de Logging

### Teste 4.1: Log de Tentativa Inválida

**Objetivo:** Validar que tentativas inválidas são logadas.

**Procedimento:**
```php
// Hook para capturar log
add_action( 'dps_portal_invalid_token_attempt', function( $log_data ) {
    error_log( 'LOG: ' . print_r( $log_data, true ) );
});

// Tentar token inválido
// Verificar error_log
```

**Resultado Esperado:**
```php
[
    'ip' => '203.0.113.42',
    'token_prefix' => '00000000...',
    'reason' => 'no_active_tokens', // ou 'token_not_found'
    'timestamp' => '2025-12-07 10:30:45',
    'user_agent' => 'Mozilla/5.0 ...',
]
```

---

### Teste 4.2: Log de Rate Limit Excedido

**Objetivo:** Validar hook de rate limit.

**Procedimento:**
```php
add_action( 'dps_portal_rate_limit_exceeded', function( $ip, $token ) {
    error_log( "Rate limit: IP=$ip, Token=" . substr($token, 0, 8) );
});

// Fazer 6 tentativas inválidas
// Verificar error_log na 6ª tentativa
```

**Resultado Esperado:**
- Hook dispara na 6ª tentativa
- Parâmetros corretos (IP, token)

---

### Teste 4.3: Retenção de Logs (30 dias)

**Objetivo:** Validar que logs são mantidos por 30 dias.

**Procedimento:**
```php
// Criar log
$log_key = 'dps_token_invalid_log_test';
set_transient( $log_key, ['test' => 'data'], 30 * DAY_IN_SECONDS );

// Verificar expiração
$expiration = get_option( '_transient_timeout_' . $log_key );
$days_diff = ( $expiration - time() ) / DAY_IN_SECONDS;
assert( $days_diff >= 29 && $days_diff <= 30 );
```

**Resultado Esperado:**
- Logs expiram em ~30 dias

---

## Testes de Integração

### Teste 5.1: Fluxo Completo de Acesso

**Objetivo:** Validar fluxo end-to-end com as melhorias.

**Cenário:**
1. Admin gera token para cliente
2. Cliente clica no link
3. Token é validado (com rate limit, IPv6, logging)
4. Sessão é criada (via transients, não $_SESSION)
5. Cliente acessa portal
6. Cliente faz logout
7. Token não pode ser reutilizado

**Procedimento:**
```bash
# Usar navegador + DevTools
# Monitorar: Network, Application/Storage, Console
```

**Resultado Esperado:**
- Fluxo completo funciona sem erros
- Nenhum aviso de `session_start()`
- Cookies `dps_portal_session` criados corretamente
- Token marcado como `used_at`

---

### Teste 5.2: Compatibilidade com Object Cache

**Objetivo:** Validar que rate limiting funciona com Redis/Memcached.

**Requisitos:**
- Redis ou Memcached configurado
- Plugin de object cache (ex: Redis Object Cache)

**Procedimento:**
```bash
# 1. Ativar object cache
wp plugin activate redis-cache

# 2. Fazer tentativas inválidas
# 3. Verificar que transients vão para Redis

# Redis CLI:
redis-cli
> KEYS *dps_token_attempts*
> GET wp:transient:dps_token_attempts_[hash]
```

**Resultado Esperado:**
- Transients armazenados em Redis
- Rate limiting funciona normalmente
- Performance melhor que DB

---

### Teste 5.3: Multi-servidor (Load Balancer)

**Objetivo:** Validar que rate limit funciona em ambiente distribuído.

**Requisitos:**
- 2+ servidores web
- Redis compartilhado (object cache)

**Procedimento:**
```bash
# Servidor 1:
curl https://servidor1.com/portal/?dps_token=INVALID
# 3 tentativas

# Servidor 2:
curl https://servidor2.com/portal/?dps_token=INVALID
# 3 tentativas

# Total: 6 tentativas do mesmo IP
# 6ª deve ser bloqueada independente do servidor
```

**Resultado Esperado:**
- Rate limit funciona entre servidores
- Redis sincroniza contadores

---

## Checklist de Validação

### Checklist Pré-Deploy

- [ ] Todos os testes de rate limiting passaram
- [ ] Todos os testes de validação de token passaram
- [ ] Todos os testes de validação de IP passaram
- [ ] Todos os testes de logging passaram
- [ ] Testes de integração executados com sucesso
- [ ] Compatibilidade com object cache verificada
- [ ] Ambiente multi-servidor testado (se aplicável)
- [ ] Performance medida (antes/depois)
- [ ] Documentação atualizada
- [ ] Code review concluído

### Checklist Pós-Deploy

- [ ] Monitorar logs por 24h
- [ ] Verificar métricas de performance
- [ ] Validar rate limiting em produção
- [ ] Confirmar ausência de erros no WP_DEBUG_LOG
- [ ] Verificar feedback de usuários
- [ ] Validar analytics de tentativas bloqueadas

---

## Relatório de Resultados

### Template de Relatório

```markdown
# Relatório de Testes de Segurança - Fase 1

**Data:** [DATA]  
**Testador:** [NOME]  
**Ambiente:** [Staging/Produção]  
**Duração:** [HORAS]

## Resumo

- **Total de testes:** X
- **Testes aprovados:** Y (Z%)
- **Testes falhados:** W
- **Bugs encontrados:** N

## Resultados por Categoria

### Rate Limiting
- Teste 1.1: ✅ Aprovado
- Teste 1.2: ✅ Aprovado
- Teste 1.3: ✅ Aprovado
- Teste 1.4: ✅ Aprovado

### Validação de Token
- Teste 2.1: ✅ Aprovado
- Teste 2.2: ✅ Aprovado
- Teste 2.3: ✅ Aprovado

### Validação de IP
- Teste 3.1: ✅ Aprovado
- Teste 3.2: ✅ Aprovado
- Teste 3.3: ✅ Aprovado
- Teste 3.4: ✅ Aprovado
- Teste 3.5: ⚠️ Observação

### Logging
- Teste 4.1: ✅ Aprovado
- Teste 4.2: ✅ Aprovado
- Teste 4.3: ✅ Aprovado

### Integração
- Teste 5.1: ✅ Aprovado
- Teste 5.2: ✅ Aprovado
- Teste 5.3: N/A (ambiente single-server)

## Performance

| Métrica | Antes | Depois | Diferença |
|---------|-------|--------|-----------|
| Validação de token (ms) | 45 | 12 | -73% |
| Tentativa bloqueada (ms) | 45 | 2 | -96% |
| Uso de memória (MB) | 2.5 | 2.7 | +8% |
| Queries por validação | 1 | 1 | 0 |

## Bugs Encontrados

1. **[Bug ID]:** Descrição do bug
   - **Severidade:** Alta/Média/Baixa
   - **Status:** Aberto/Resolvido
   - **Fix:** Link para commit

## Observações

- Cache negativo melhora performance em 90%
- Rate limiting efetivo contra brute force
- IPv6 detectado corretamente
- Logs detalhados ajudam debug

## Recomendação

✅ **APROVADO PARA PRODUÇÃO**

Todas as melhorias de segurança funcionam conforme esperado. Recomenda-se deploy imediato.

## Próximos Passos

1. Monitorar métricas pós-deploy
2. Implementar Fase 2 de melhorias
3. Considerar adição de CAPTCHA (Fase 3)
```

---

## Ferramentas de Automação

### Script de Teste Automatizado

```bash
#!/bin/bash
# test-security-phase1.sh

# Cores
RED='\033[0;31m'
GREEN='\033[0;32m'
NC='\033[0m' # No Color

SITE_URL="https://seu-site.com"
PORTAL_URL="$SITE_URL/portal/"
PASS=0
FAIL=0

echo "========================================="
echo "  Testes de Segurança - Fase 1"
echo "========================================="

# Teste 1: Rate Limiting
echo -e "\n[Teste 1] Rate Limiting..."
INVALID_TOKEN="0000000000000000000000000000000000000000000000000000000000000000"

for i in {1..6}; do
  HTTP_CODE=$(curl -s -o /dev/null -w "%{http_code}" "$PORTAL_URL?dps_token=$INVALID_TOKEN")
  if [ $i -le 5 ]; then
    if [ $HTTP_CODE -eq 200 ] || [ $HTTP_CODE -eq 302 ]; then
      echo "  Tentativa $i: OK"
    else
      echo -e "${RED}  Tentativa $i: FALHOU (HTTP $HTTP_CODE)${NC}"
      ((FAIL++))
    fi
  else
    # 6ª tentativa deve ser bloqueada
    if [ $HTTP_CODE -ne 200 ]; then
      echo -e "${GREEN}  ✓ Rate limit funcionou (6ª tentativa bloqueada)${NC}"
      ((PASS++))
    else
      echo -e "${RED}  ✗ Rate limit NÃO funcionou (6ª tentativa permitida)${NC}"
      ((FAIL++))
    fi
  fi
  sleep 1
done

# Resumo
echo -e "\n========================================="
echo -e "RESUMO: ${GREEN}$PASS aprovados${NC}, ${RED}$FAIL falhados${NC}"
echo "========================================="

if [ $FAIL -eq 0 ]; then
  echo -e "${GREEN}✓ Todos os testes passaram!${NC}"
  exit 0
else
  echo -e "${RED}✗ Alguns testes falharam.${NC}"
  exit 1
fi
```

### Uso:
```bash
chmod +x test-security-phase1.sh
./test-security-phase1.sh
```

---

## Conclusão

Este documento fornece um framework completo para validar as melhorias de segurança da Fase 1. Todos os testes devem ser executados antes do deploy em produção.

**Próximas Fases:**
- **Fase 2:** Rotação de tokens, logout POST, melhorias UX
- **Fase 3:** 2FA, CAPTCHA, analytics avançado
- **Fase 4:** PWA, notificações push

**Contato:**
- Dúvidas sobre testes: Equipe de QA
- Bugs encontrados: GitHub Issues
- Melhorias sugeridas: Pull Requests

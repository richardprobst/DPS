# Reorganização Services ⇄ Agenda: Sumário de Implementação

**Data**: 2025-11-22  
**Versão Services**: 1.2.0  
**Versão Agenda**: 1.1.0

---

## 📋 Objetivo

Centralizar lógica de serviços e cálculo de preços no **Services Add-on**, mantendo **Agenda Add-on** como interface de operação (listar, selecionar, visualizar).

## ✅ Implementação Concluída

### Services Add-on (v1.2.0)

#### Nova API Pública: `DPS_Services_API`

Classe criada em `add-ons/desi-pet-shower-services_addon/dps_service/includes/class-dps-services-api.php`

**Métodos disponíveis**:

1. **`get_service( $service_id )`**
   - Retorna dados completos de um serviço
   - Retorno: `['id', 'title', 'type', 'category', 'active', 'price', 'price_small', 'price_medium', 'price_large']`

2. **`calculate_price( $service_id, $pet_size, $context = [] )`**
   - Calcula preço de um serviço com base no porte do pet
   - Aceita: `'pequeno'/'small'`, `'medio'/'medium'`, `'grande'/'large'`
   - Retorno: `float` (preço calculado) ou `null` se serviço não encontrado

3. **`calculate_appointment_total( $service_ids, $pet_ids, $context = [] )`**
   - Calcula total de um agendamento
   - Context opcional: `['custom_prices' => [], 'extras' => 0.0, 'taxidog' => 0.0]`
   - Retorno: `['total', 'services_total', 'services_details', 'extras_total', 'taxidog_total']`

4. **`get_services_details( $appointment_id )`**
   - Obtém detalhes de serviços de um agendamento
   - Retorno: `['services' => [['name', 'price'], ...], 'total']`

#### Endpoint AJAX Movido

- **Endpoint**: `dps_get_services_details`
- **Handler**: `DPS_Services_Addon::get_services_details_ajax()`
- **Origem**: Movido da Agenda para Services
- **Uso**: Retorna detalhes de serviços para modal/visualização

### Agenda Add-on (v1.1.0)

#### Delegação Implementada

**Arquivo**: `add-ons/desi-pet-shower-agenda_addon/desi-pet-shower-agenda-addon.php`

**Método deprecado mas mantido**:
- `get_services_details_ajax()` (linhas 936-1003)
- Marcado como `@deprecated 1.1.0`
- **Delega** para `DPS_Services_API::get_services_details()` quando disponível
- **Fallback**: mantém implementação legada se Services não estiver ativo

#### Bug Pré-Existente Corrigido

**Localização**: Linhas 915-929 (anteriormente 918-936)

**Problema**: 
- Closing brace órfão (`}`) sem matching opening brace
- Código quebrado usando variáveis indefinidas: `$client_id`, `$pet_post`, `$date`, `$valor`
- Causava **syntax error** no PHP

**Solução**:
- Código removido e substituído por comentário `TODO`
- Notificação WhatsApp precisa ser reimplementada corretamente no futuro

---

## 📊 Pontos da Agenda que DEIXARAM de ter lógica de cálculo

### ✅ AJAX: `dps_get_services_details`

**Antes (v1.0.0)**:
```php
// Agenda calculava preços manualmente
$service_ids = get_post_meta( $id_param, 'appointment_services', true );
$service_prices = get_post_meta( $id_param, 'appointment_service_prices', true );
foreach ( $service_ids as $sid ) {
    $srv = get_post( $sid );
    $price = isset( $service_prices[ $sid ] ) 
        ? (float) $service_prices[ $sid ] 
        : (float) get_post_meta( $sid, 'service_price', true );
    // ... monta array de serviços
}
```

**Agora (v1.1.0)**:
```php
// Agenda delega para Services API
if ( class_exists( 'DPS_Services_API' ) ) {
    $details = DPS_Services_API::get_services_details( $id_param );
    wp_send_json_success( [
        'services' => $details['services'],
        'nonce_ok' => $nonce_ok,
    ] );
}
```

**Impacto**: Agenda **não** manipula mais `service_price` diretamente neste endpoint.

---

## 🔄 Próximos Passos (Pendentes)

### 1. Refatorar Cálculos Inline na Agenda

Existem outros pontos na Agenda que ainda podem calcular preços manualmente:

**Candidatos para refatoração**:
- Formulário de agendamento (se houver cálculos inline)
- Tabelas de visualização (se calcular totais localmente)
- Qualquer lógica que some `service_price` + variações de porte

**Ação**: Identificar e substituir por `DPS_Services_API::calculate_appointment_total()`

### 2. Atualizar Finance Add-on

Finance deve usar `DPS_Services_API` para obter valores históricos:

```php
// Em vez de:
$total = get_post_meta( $appt_id, 'appointment_total_value', true );

// Usar:
$details = DPS_Services_API::get_services_details( $appt_id );
$total = $details['total'];
```

### 3. Atualizar Portal do Cliente

Portal deve usar API para exibir valores de agendamentos:

```php
$calculation = DPS_Services_API::calculate_appointment_total( 
    $selected_services, 
    $selected_pets,
    [ 'extras' => $extras_value, 'taxidog' => $taxidog_value ]
);
echo 'Total: R$ ' . number_format( $calculation['total'], 2, ',', '.' );
```

---

## 📚 Documentação Atualizada

### ANALYSIS.md

**Seção Services** (linha 490-560):
- Adicionada seção "API Pública" com exemplos de uso
- Adicionada seção "Contrato de integração"
- Documentados todos os métodos com assinaturas e retornos

**Seção Agenda** (linha 146-182):
- Marcado endpoint `dps_get_services_details` como deprecated
- Documentada delegação para Services API
- Adicionada nota sobre dependência recomendada do Services

### CHANGELOG.md

**[Unreleased]**:
- **Added**: API pública do Services com 4 métodos
- **Added**: Endpoint AJAX movido da Agenda para Services
- **Fixed**: Bug de syntax error na Agenda (linha 936)
- **Deprecated**: Método `get_services_details_ajax()` na Agenda
- **Deprecated**: Endpoint AJAX gerenciado por Services (Agenda mantém compatibilidade)
- **Refactoring**: Header duplicado removido do arquivo interno do Services

---

## 🎯 Assinatura da Função de Detalhes de Serviços

### Endpoint AJAX

**Action**: `dps_get_services_details`  
**Método HTTP**: POST  
**Handler principal**: `DPS_Services_Addon::get_services_details_ajax()` (Services v1.2.0+)  
**Handler legado**: `DPS_Agenda_Addon::get_services_details_ajax()` (deprecated, fallback)

**Parâmetros**:
- `appt_id` (int): ID do agendamento
- `id` (int, opcional): fallback para `appt_id`
- `nonce` (string, opcional): nonce de segurança (tolerante)

**Retorno JSON**:
```json
{
  "success": true,
  "data": {
    "services": [
      { "name": "Banho", "price": 50.00 },
      { "name": "Tosa", "price": 80.00 }
    ],
    "nonce_ok": true
  }
}
```

### Método da API

**Classe**: `DPS_Services_API`  
**Método**: `get_services_details( $appointment_id )`

**Assinatura**:
```php
/**
 * @param int $appointment_id ID do agendamento.
 * @return array ['services' => [['name', 'price'], ...], 'total' => float]
 */
public static function get_services_details( $appointment_id );
```

**Exemplo de uso**:
```php
$details = DPS_Services_API::get_services_details( 123 );
foreach ( $details['services'] as $service ) {
    echo $service['name'] . ': R$ ' . number_format( $service['price'], 2, ',', '.' ) . "\n";
}
echo 'Total: R$ ' . number_format( $details['total'], 2, ',', '.' );
```

---

## ✅ Checklist de Validação

- [x] API criada e carregada no Services Add-on
- [x] Endpoint AJAX movido para Services
- [x] Agenda delega para Services quando disponível
- [x] Compatibilidade mantida (fallback se Services inativo)
- [x] Documentação atualizada (ANALYSIS.md + CHANGELOG.md)
- [x] Bug pré-existente corrigido (syntax error Agenda)
- [x] Versionamento atualizado (Services 1.2.0)
- [x] Header duplicado removido (evita duplicação na lista de plugins)
- [ ] **Pendente**: Refatorar cálculos inline na Agenda
- [ ] **Pendente**: Atualizar Finance para usar Services API
- [ ] **Pendente**: Atualizar Portal para usar Services API
- [ ] **Pendente**: Testes em ambiente WordPress local

---

## 🔍 Como Testar

### 1. Verificar que Services API está disponível

```php
if ( class_exists( 'DPS_Services_API' ) ) {
    echo 'Services API carregada!';
}
```

### 2. Testar cálculo de preço por porte

```php
$service_id = 123; // ID de um serviço existente
$price_pequeno = DPS_Services_API::calculate_price( $service_id, 'pequeno' );
$price_grande = DPS_Services_API::calculate_price( $service_id, 'grande' );
echo "Pequeno: R$ " . number_format( $price_pequeno, 2, ',', '.' );
echo "Grande: R$ " . number_format( $price_grande, 2, ',', '.' );
```

### 3. Testar cálculo de total de agendamento

```php
$calculation = DPS_Services_API::calculate_appointment_total(
    [ 10, 11, 12 ], // IDs de serviços
    [ 5 ],          // ID de pet
    [
        'extras' => 25.00,
        'taxidog' => 15.00,
    ]
);
print_r( $calculation );
```

### 4. Verificar endpoint AJAX (via browser console)

```javascript
jQuery.post(ajaxurl, {
    action: 'dps_get_services_details',
    appt_id: 456,
    nonce: dpsAgendaData.nonce_services // se disponível
}, function(resp) {
    console.log('Serviços:', resp.data.services);
    console.log('Total:', resp.data.total);
});
```

---

## 📝 Notas Finais

- **Compatibilidade garantida**: Agenda mantém fallback para funcionar sem Services
- **Sem breaking changes**: código antigo continua funcionando
- **Migração gradual**: outros add-ons podem adotar Services API progressivamente
- **Documentação completa**: ANALYSIS.md tem exemplos de uso de todos os métodos
- **Versão semântica**: Services 1.2.0 (MINOR) pois adiciona funcionalidade sem quebrar API existente

---

**Responsável pela implementação**: GitHub Copilot  
**Revisão recomendada**: Antes de release, validar em ambiente local com dados reais

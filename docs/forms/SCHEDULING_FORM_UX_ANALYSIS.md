# Análise de UX - Formulários de Agendamento de Banho e Tosa (DPS)

**Data:** 21/11/2024  
**Versão:** 1.0  
**Escopo:** Formulários de agendamento de serviços (Banho e Tosa)

---

## 1. FORMULÁRIOS IDENTIFICADOS

### 1.1. Admin - Formulário de Agendamento Principal

**Localização:** `plugins/desi-pet-shower-base/includes/class-dps-base-frontend.php`  
**Método:** `section_agendas()` (linhas 1095-1424)  
**Contexto de uso:** Interface administrativa WordPress (wp-admin ou área protegida)  
**Shortcode:** `[dps_base]` (aba "Agendamentos")

**Características atuais:**
- Formulário único, sem multi-etapas
- Campos organizados verticalmente em sequência linear
- Suporta dois tipos de agendamento: simples e assinatura
- Permite seleção múltipla de pets
- Integração com add-ons de serviços via hook `dps_base_appointment_fields`

### 1.2. Portal do Cliente - Visualização (não-formulário)

**Localização:** `plugins/desi-pet-shower-client-portal/includes/class-dps-client-portal.php`  
**Método:** `render_next_appointment()` (linhas 607-690)  
**Contexto de uso:** Área pública para clientes logados  
**Shortcode:** `[dps_client_portal]`

**Características atuais:**
- **NÃO É UM FORMULÁRIO** - apenas exibe próximo agendamento
- Mostra card visual com data, horário, pet e serviços
- Link para WhatsApp quando não há agendamentos futuros
- Não permite criação/edição de agendamentos pelo cliente

---

## 2. ANÁLISE DETALHADA DO FORMULÁRIO ADMIN

### 2.1. FLUXO DE PREENCHIMENTO

#### ✅ PONTOS POSITIVOS

**Ordem lógica dos campos principais:**
1. Tipo de agendamento (simples vs assinatura)
2. Cliente
3. Pet(s)
4. Data
5. Horário
6. Observações

**Recursos avançados:**
- Pré-seleção de cliente/pet via parâmetros URL (`pref_client`, `pref_pet`)
- Alerta visual de pendências financeiras ao selecionar cliente
- Busca e filtro de pets por nome, raça ou tutor
- Contador visual de pets selecionados
- Validação HTML5 em campos obrigatórios

#### ❌ PROBLEMAS IDENTIFICADOS

**1. Organização confusa de campos condicionais**

Campos específicos de assinatura aparecem/desaparecem via JavaScript:
- `#dps-appointment-frequency-wrapper` (frequência: semanal/quinzenal)
- `#dps-tosa-wrapper` (necessidade de tosa + preço + ocorrência)
- Campos de serviços extras (via add-ons)

**Problema:** O formulário "pula" visualmente quando o usuário alterna entre tipos.

**2. Falta de agrupamento visual (fieldsets)**

Todos os campos estão soltos em `<p>` sequenciais. Não há fieldsets para agrupar:
- Dados do agendamento (Cliente, Pet, Data, Horário)
- Serviços e Extras (TaxiDog, Tosa, Serviços customizados)
- Observações e Notas

**3. Seletor de pets complexo demais**

O componente `.dps-pet-picker` (linhas 1267-1310):
- Usa grid com cards clicáveis
- Busca em tempo real
- Paginação com "Carregar mais pets"
- Botões "Selecionar todos" e "Limpar seleção"

**Problema:** Para agendamentos simples (1 pet), componente é excessivo.

**4. Campos de data/horário sem validação avançada**

```html
<input type="date" name="appointment_date" required>
<input type="time" name="appointment_time" required>
```

**Problemas:**
- Não valida se a data/hora já tem agendamento conflitante
- Não mostra horários disponíveis vs indisponíveis
- Não bloqueia datas passadas (apenas validação HTML5 básica)

**5. Falta de resumo antes de salvar**

O formulário vai direto para submit sem mostrar resumo dos dados.

---

### 2.2. ESCOLHA DE DATA/HORÁRIO

#### ❌ PROBLEMAS CRÍTICOS

**1. Componente nativo sem customização**

Inputs nativos `type="date"` e `type="time"` não mostram disponibilidade.

**2. Sem indicação visual de disponibilidade**

Não há:
- Calendário com datas bloqueadas/disponíveis
- Lista de horários já ocupados
- Sugestão de próximo horário disponível

**3. Campos TaxiDog e Tosa sem contexto claro**

Aparecem com `style="display:none"` inline, controlado via JavaScript.

---

### 2.3. RESUMO E CONFIRMAÇÃO

#### ❌ PROBLEMA CRÍTICO: SEM RESUMO

O formulário não possui seção de resumo antes do submit.

**Fluxo atual:**
1. Usuário preenche campos
2. Clica em "Salvar Agendamento"
3. Feedback só aparece após recarregar a página

**Fluxo ideal:**
1. Usuário preenche campos
2. Vê resumo: cliente, pet(s), data, horário, serviços, valor
3. Confirma ou volta para editar
4. Sistema salva e mostra feedback

---

### 2.4. LAYOUT E RESPONSIVIDADE

#### ❌ PROBLEMAS DE LAYOUT

**1. Campos verticais demais - sem uso de grid**

Desperdício de espaço horizontal em desktop:
- Data e Horário poderiam estar lado a lado
- TaxiDog e Tosa poderiam estar em grid 2 colunas

**2. Falta de classes responsivas**

O CSS tem `.dps-form-row--2col` mas não está aplicado neste formulário.

**3. Mobile: campos minúsculos**

Em telas < 640px inputs de data/hora ficam difíceis de usar.


---

## 3. ESTILO VISUAL (MINIMALISTA/CLEAN)

### 3.1. ELEMENTOS NÃO-MINIMALISTAS

#### ❌ PROBLEMAS IDENTIFICADOS

**1. Inline styles espalhados no código**

```html
<span style="margin-left:10px; display:none;">
<label style="margin-left:20px;">
<div style="display:none;">
```

**2. Alert de pendências muito destacado**

Borda vermelha (`#ef4444`) muito agressiva. Poderia ser colapsável.

**3. Falta de hierarquia visual**

- Todos os campos têm o mesmo peso visual
- Campos obrigatórios sem asterisco vermelho (`.dps-required`)
- Sem agrupamento em fieldsets (`.dps-fieldset`)

**4. JavaScript inline no PHP**

Linhas 1358-1423 contêm `<script>` embutido usando heredoc.

---

## 4. PROPOSTA DE MELHORIAS

### 4.1. MELHORIAS PRIORITÁRIAS

#### 1. Organizar em fieldsets lógicos

```html
<fieldset class="dps-fieldset">
    <legend class="dps-fieldset__legend">Tipo de Agendamento</legend>
    <!-- campos -->
</fieldset>

<fieldset class="dps-fieldset">
    <legend class="dps-fieldset__legend">Cliente e Pet(s)</legend>
    <!-- campos -->
</fieldset>

<fieldset class="dps-fieldset">
    <legend class="dps-fieldset__legend">Data e Horário</legend>
    <div class="dps-form-row--2col">
        <!-- data e horário lado a lado -->
    </div>
</fieldset>

<fieldset class="dps-fieldset">
    <legend class="dps-fieldset__legend">Serviços e Extras</legend>
    <!-- TaxiDog, Tosa, etc -->
</fieldset>

<fieldset class="dps-fieldset">
    <legend class="dps-fieldset__legend">Observações</legend>
    <!-- textarea -->
</fieldset>
```

**Arquivos a modificar:**
- `class-dps-base-frontend.php`: refatorar método `section_agendas()`
- `dps-base.css`: estilos já existem, apenas aplicar

---

#### 2. Adicionar resumo antes de salvar

```html
<div class="dps-appointment-summary" id="dps-appointment-summary" style="display:none;">
    <h3>Resumo do Agendamento</h3>
    
    <div class="dps-summary-grid">
        <div class="dps-summary-item">
            <span class="dps-summary-label">Cliente:</span>
            <span class="dps-summary-value" id="summary-client">-</span>
        </div>
        
        <div class="dps-summary-item">
            <span class="dps-summary-label">Pet(s):</span>
            <span class="dps-summary-value" id="summary-pets">-</span>
        </div>
        
        <div class="dps-summary-item">
            <span class="dps-summary-label">Data e Horário:</span>
            <span class="dps-summary-value" id="summary-datetime">-</span>
        </div>
        
        <div class="dps-summary-item dps-summary-item--highlight">
            <span class="dps-summary-label">Valor Total:</span>
            <span class="dps-summary-value" id="summary-total">R$ 0,00</span>
        </div>
    </div>
</div>
```

**CSS minimalista:**
```css
.dps-appointment-summary {
    background: #f9fafb;
    border: 1px solid #e5e7eb;
    border-radius: 8px;
    padding: 20px;
    margin: 20px 0;
}

.dps-summary-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 16px;
}

.dps-summary-label {
    font-size: 12px;
    color: #6b7280;
    text-transform: uppercase;
}

.dps-summary-value {
    font-size: 16px;
    color: #374151;
    font-weight: 600;
}

.dps-summary-item--highlight .dps-summary-value {
    font-size: 20px;
    color: #0ea5e9;
}
```

**JavaScript (atualização em tempo real):**
```javascript
function updateAppointmentSummary() {
    const client = $('#dps-appointment-cliente option:selected').text();
    const pets = $('.dps-pet-checkbox:checked').map(function() {
        return $(this).siblings('.dps-pet-name').text();
    }).get().join(', ');
    const date = $('input[name="appointment_date"]').val();
    const time = $('input[name="appointment_time"]').val();
    
    $('#summary-client').text(client || '-');
    $('#summary-pets').text(pets || '-');
    $('#summary-datetime').text(date && time ? `${formatDate(date)} às ${time}` : '-');
    
    if (client && pets && date && time) {
        $('#dps-appointment-summary').slideDown();
    }
}
```

**Arquivos a modificar:**
- `class-dps-base-frontend.php`: adicionar HTML do resumo
- `dps-base.css`: adicionar estilos do resumo
- Novo: `dps-appointment-form.js`: lógica de atualização

---

#### 3. Melhorar seletor de data/horário

**Proposta - Busca de horários disponíveis via AJAX:**

```html
<div class="dps-form-row--2col">
    <div class="dps-form-field">
        <label for="appointment_date">Data <span class="dps-required">*</span></label>
        <input type="date" id="appointment_date" name="appointment_date" required>
        <p class="dps-field-hint">Horários disponíveis serão carregados após escolher a data</p>
    </div>
    
    <div class="dps-form-field">
        <label for="appointment_time">Horário <span class="dps-required">*</span></label>
        <select id="appointment_time" name="appointment_time" required>
            <option value="">Escolha uma data primeiro</option>
        </select>
    </div>
</div>
```

**AJAX Handler (PHP):**
```php
add_action( 'wp_ajax_dps_get_available_times', [ $this, 'get_available_times_ajax' ] );

public function get_available_times_ajax() {
    check_ajax_referer( 'dps_action', 'nonce' );
    
    $date = sanitize_text_field( $_POST['date'] ?? '' );
    
    // Buscar agendamentos do dia
    $appointments = get_posts([
        'post_type' => 'dps_agendamento',
        'meta_key' => 'appointment_date',
        'meta_value' => $date,
        'posts_per_page' => -1
    ]);
    
    $occupied_times = [];
    foreach ($appointments as $appt) {
        $time = get_post_meta($appt->ID, 'appointment_time', true);
        if ($time) $occupied_times[] = $time;
    }
    
    // Horários de trabalho (08:00 às 18:00, intervalos de 30min)
    $all_times = [];
    for ($hour = 8; $hour <= 18; $hour++) {
        foreach (['00', '30'] as $min) {
            $time = sprintf('%02d:%s', $hour, $min);
            if ($hour == 18 && $min == '30') break;
            
            $status = in_array($time, $occupied_times) ? 'ocupado' : 'disponível';
            $all_times[] = [
                'value' => $time,
                'label' => $time . ' - ' . $status,
                'available' => !in_array($time, $occupied_times)
            ];
        }
    }
    
    wp_send_json_success(['times' => $all_times]);
}
```

**JavaScript:**
```javascript
$('#appointment_date').on('change', function() {
    const date = $(this).val();
    const $timeSelect = $('#appointment_time');
    
    $timeSelect.prop('disabled', true).html('<option>Carregando...</option>');
    
    $.post(ajaxurl, {
        action: 'dps_get_available_times',
        nonce: dpsData.nonce,
        date: date
    }, function(response) {
        if (response.success) {
            let html = '<option value="">Selecione um horário</option>';
            
            response.data.times.forEach(function(time) {
                if (time.available) {
                    html += `<option value="${time.value}">${time.label}</option>`;
                }
            });
            
            $timeSelect.html(html).prop('disabled', false);
        }
    });
});
```

**Arquivos a modificar:**
- `class-dps-base-frontend.php`: adicionar AJAX handler + alterar input time para select
- Novo: `dps-appointment-form.js`: busca de horários

---

#### 4. Mover JavaScript inline para arquivo separado

**Problema:** Linhas 1358-1423 têm JavaScript inline

**Solução:**

Criar arquivo `assets/js/dps-appointment-form.js`:

```javascript
(function($) {
    'use strict';
    
    const DPSAppointmentForm = {
        init: function() {
            this.bindEvents();
            this.updateTypeFields();
            this.updateTosaOptions();
        },
        
        bindEvents: function() {
            $(document).on('change', 'input[name="appointment_type"]', 
                this.updateTypeFields.bind(this));
            $('#appointment_frequency').on('change', 
                this.updateTosaOptions.bind(this));
            $('#dps-taxidog-toggle').on('change', 
                this.toggleTaxiDog.bind(this));
            $('#dps-tosa-toggle').on('change', 
                this.toggleTosa.bind(this));
        },
        
        updateTypeFields: function() {
            const type = $('input[name="appointment_type"]:checked').val();
            $('#dps-appointment-frequency-wrapper').toggle(type === 'subscription');
            $('#dps-tosa-wrapper').toggle(type === 'subscription');
            this.toggleTaxiDog();
        },
        
        toggleTaxiDog: function() {
            const type = $('input[name="appointment_type"]:checked').val();
            const hasTaxi = $('#dps-taxidog-toggle').is(':checked');
            
            if (type === 'subscription') {
                $('#dps-taxidog-extra').hide();
            } else {
                $('#dps-taxidog-extra').toggle(hasTaxi);
            }
        },
        
        toggleTosa: function() {
            const show = $('#dps-tosa-toggle').is(':checked');
            $('#dps-tosa-fields').toggle(show);
        },
        
        updateTosaOptions: function() {
            const freq = $('#appointment_frequency').val() || 'semanal';
            const select = $('#appointment_tosa_occurrence');
            const occurrences = (freq === 'quinzenal') ? 2 : 4;
            const current = select.data('current');
            
            select.empty();
            for (let i = 1; i <= occurrences; i++) {
                select.append(`<option value="${i}">${i}º Atendimento</option>`);
            }
            
            if (current && current <= occurrences) {
                select.val(current);
            }
        }
    };
    
    $(document).ready(function() {
        DPSAppointmentForm.init();
    });
    
})(jQuery);
```

**Enqueue no PHP:**
```php
wp_enqueue_script(
    'dps-appointment-form',
    DPS_PLUGIN_URL . 'assets/js/dps-appointment-form.js',
    array('jquery'),
    DPS_VERSION,
    true
);

wp_localize_script('dps-appointment-form', 'dpsData', [
    'nonce' => wp_create_nonce('dps_action'),
    'ajaxurl' => admin_url('admin-ajax.php')
]);
```

**Arquivos:**
- Remover: JavaScript inline de `class-dps-base-frontend.php` (linhas 1358-1423)
- Criar: `assets/js/dps-appointment-form.js`

---

#### 5. Marcar campos obrigatórios com asterisco

**Aplicar em todos os labels:**

```html
<label for="appointment_client_id">
    Cliente <span class="dps-required">*</span>
</label>

<label for="appointment_date">
    Data <span class="dps-required">*</span>
</label>
```

**CSS já existe:**
```css
.dps-required {
    color: #ef4444;
    font-weight: 700;
}
```

---

#### 6. Aplicar grid responsivo

**HTML:**
```html
<div class="dps-form-row dps-form-row--2col">
    <div class="dps-form-field">
        <label>Data <span class="dps-required">*</span></label>
        <input type="date" name="appointment_date" required>
    </div>
    
    <div class="dps-form-field">
        <label>Horário <span class="dps-required">*</span></label>
        <input type="time" name="appointment_time" required>
    </div>
</div>
```

**CSS (já existe em dps-base.css):**
```css
.dps-form-row--2col {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 16px;
}

@media (max-width: 640px) {
    .dps-form-row--2col {
        grid-template-columns: 1fr;
    }
}
```

---

### 4.2. MELHORIAS SECUNDÁRIAS

#### 7. Adicionar tooltips explicativos

```html
<label class="dps-label-with-tooltip">
    Precisa de tosa?
    <span class="dps-tooltip" data-tooltip="Serviço de tosa incluso na assinatura">ℹ️</span>
</label>
```

**CSS:**
```css
.dps-tooltip {
    position: relative;
    cursor: help;
    color: #6b7280;
}

.dps-tooltip[data-tooltip]:hover::after {
    content: attr(data-tooltip);
    position: absolute;
    bottom: 100%;
    left: 50%;
    transform: translateX(-50%);
    background: #374151;
    color: #fff;
    padding: 8px 12px;
    border-radius: 4px;
    font-size: 12px;
    white-space: normal;
    max-width: 200px;
    z-index: 10;
}
```

---

#### 8. Desabilitar botão durante submit

```javascript
$('form.dps-form').on('submit', function() {
    const $btn = $(this).find('.dps-submit-btn');
    $btn.prop('disabled', true).text('Salvando...');
    
    setTimeout(function() {
        $btn.prop('disabled', false).text('Salvar Agendamento');
    }, 5000);
});
```

---

## 5. ARQUIVOS A MODIFICAR

### PHP

1. **`class-dps-base-frontend.php`**
   - Refatorar método `section_agendas()` (linhas 1095-1424)
   - Adicionar fieldsets
   - Aplicar grid responsivo
   - Adicionar resumo
   - Marcar campos obrigatórios
   - Remover JavaScript inline
   - Adicionar AJAX handler para horários

### JavaScript

2. **Novo: `assets/js/dps-appointment-form.js`**
   - Mover lógica de campos condicionais
   - Atualização de resumo em tempo real
   - Busca de horários disponíveis

### CSS

3. **`assets/css/dps-base.css`**
   - Adicionar estilos para `.dps-appointment-summary`
   - Adicionar estilos para `.dps-tooltip`
   - Garantir que `.dps-form-row--2col` funcione

---

## 6. CRONOGRAMA

### Fase 1 - Organização Visual (Rápido - 1 dia)

- ✅ Adicionar fieldsets
- ✅ Aplicar grid responsivo
- ✅ Marcar campos obrigatórios
- ✅ Mover JavaScript para arquivo separado

### Fase 2 - Melhorias de UX (Médio - 2 dias)

- ✅ Implementar resumo dinâmico
- ✅ Busca de horários disponíveis
- ✅ Tooltips explicativos
- ✅ Desabilitar botão durante submit

---

## 7. PRIORIZAÇÃO

### MUST HAVE (Críticas)

1. ✅ Fieldsets para organização
2. ✅ Asteriscos em campos obrigatórios
3. ✅ Grid responsivo (data/hora lado a lado)
4. ✅ Mover JavaScript inline
5. ✅ Resumo antes de salvar

### SHOULD HAVE (Importantes)

6. ✅ Busca de horários disponíveis
7. ✅ Tooltips
8. ✅ Desabilitar botão durante submit

### COULD HAVE (Opcionais)

9. ⚠️ Wizard multi-etapas
10. ⚠️ Calendário visual (Flatpickr)

---

## 8. CONCLUSÃO

O formulário de agendamento é **funcional** mas pode ser **significativamente melhorado**.

**Principais problemas:**
1. Falta de agrupamento visual (sem fieldsets)
2. Campos de data/horário sem indicação de disponibilidade
3. Ausência de resumo antes de salvar
4. JavaScript inline dificulta manutenção
5. Layout não aproveita espaço horizontal

**Ganhos esperados:**
- ✅ Redução de erros de preenchimento
- ✅ Melhor compreensão do fluxo
- ✅ Experiência mais profissional
- ✅ Alinhamento com estilo minimalista
- ✅ Melhor responsividade mobile

**Recomendação:**
Implementar melhorias em **2 fases** (Organização Visual → Melhorias de UX), priorizando as de maior impacto.


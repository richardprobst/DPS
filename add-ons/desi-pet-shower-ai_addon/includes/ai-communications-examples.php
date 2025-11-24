<?php
/**
 * Exemplos de Uso do Assistente de IA para Comunicações
 *
 * Este arquivo demonstra como integrar os botões de sugestão de IA
 * em diferentes contextos: Agenda, Cobranças, Notificações, etc.
 *
 * IMPORTANTE: Estes são EXEMPLOS de código. Não incluir diretamente
 * em produção sem adaptar ao contexto específico.
 *
 * @package DPS_AI_Addon
 * @since 1.2.0
 */

// Este arquivo NÃO deve ser carregado diretamente.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * EXEMPLO 1: Lembrete de Agendamento via WhatsApp
 *
 * Contexto: Página de agenda onde o usuário pode enviar lembrete para o cliente.
 */
function example_agenda_reminder_whatsapp() {
    // Supondo que temos dados do agendamento
    $appointment_id   = 123;
    $client_name      = 'João Silva';
    $pet_name         = 'Rex';
    $appointment_date = '15/12/2024';
    $appointment_time = '14:00';
    $services         = [ 'Banho', 'Tosa' ];
    $client_phone     = '11987654321';

    ?>
    <div class="dps-reminder-form">
        <h3>Enviar Lembrete via WhatsApp</h3>
        
        <label for="whatsapp-message-<?php echo esc_attr( $appointment_id ); ?>">
            Mensagem para o Cliente:
        </label>
        <textarea 
            id="whatsapp-message-<?php echo esc_attr( $appointment_id ); ?>"
            name="whatsapp_message"
            rows="4"
            class="widefat"
            placeholder="Digite a mensagem ou clique em 'Sugerir com IA'..."
        ></textarea>
        
        <div style="margin-top: 10px;">
            <!-- Botão de sugestão de IA -->
            <button 
                type="button"
                class="button dps-ai-suggest-whatsapp"
                data-target="#whatsapp-message-<?php echo esc_attr( $appointment_id ); ?>"
                data-type="lembrete"
                data-client-name="<?php echo esc_attr( $client_name ); ?>"
                data-pet-name="<?php echo esc_attr( $pet_name ); ?>"
                data-appointment-date="<?php echo esc_attr( $appointment_date ); ?>"
                data-appointment-time="<?php echo esc_attr( $appointment_time ); ?>"
                data-services='<?php echo esc_attr( wp_json_encode( $services ) ); ?>'
            >
                Sugerir com IA
            </button>
            
            <!-- Botão de envio (abre WhatsApp, NÃO envia automaticamente) -->
            <a 
                href="#"
                class="button button-primary"
                onclick="openWhatsAppWithMessage('<?php echo esc_js( $client_phone ); ?>', document.getElementById('whatsapp-message-<?php echo esc_js( $appointment_id ); ?>').value); return false;"
            >
                Abrir WhatsApp
            </a>
        </div>
    </div>
    
    <script>
    function openWhatsAppWithMessage(phone, message) {
        if (!message || message.trim() === '') {
            alert('Por favor, escreva ou gere uma mensagem antes de abrir o WhatsApp.');
            return;
        }
        
        // Formata número e abre WhatsApp usando número do cliente
        var formattedPhone = phone.replace(/\D/g, '');
        if (formattedPhone.length >= 10 && formattedPhone.length <= 11) {
            // Adiciona código do país (55) se não existir
            if (!formattedPhone.startsWith('55')) {
                formattedPhone = '55' + formattedPhone;
            }
        }
        
        var url = 'https://wa.me/' + formattedPhone + '?text=' + encodeURIComponent(message);
        window.open(url, '_blank');
    }
    </script>
    <?php
}

/**
 * EXEMPLO 2: E-mail de Pós-Atendimento
 *
 * Contexto: Após concluir um atendimento, enviar e-mail de agradecimento.
 */
function example_post_service_email() {
    // Dados do atendimento
    $appointment_id   = 456;
    $client_name      = 'Maria Santos';
    $client_email     = 'maria@example.com';
    $pet_name         = 'Mel';
    $appointment_date = '10/12/2024';
    $services         = [ 'Banho', 'Hidratação' ];

    ?>
    <div class="dps-email-form">
        <h3>Enviar E-mail de Agradecimento</h3>
        
        <div class="form-field">
            <label for="email-to-<?php echo esc_attr( $appointment_id ); ?>">Para:</label>
            <input 
                type="email" 
                id="email-to-<?php echo esc_attr( $appointment_id ); ?>"
                name="email_to"
                value="<?php echo esc_attr( $client_email ); ?>"
                class="widefat"
                readonly
            />
        </div>
        
        <div class="form-field">
            <label for="email-subject-<?php echo esc_attr( $appointment_id ); ?>">Assunto:</label>
            <input 
                type="text" 
                id="email-subject-<?php echo esc_attr( $appointment_id ); ?>"
                name="email_subject"
                class="widefat"
                placeholder="Digite o assunto ou clique em 'Sugerir E-mail com IA'..."
            />
        </div>
        
        <div class="form-field">
            <label for="email-body-<?php echo esc_attr( $appointment_id ); ?>">Mensagem:</label>
            <textarea 
                id="email-body-<?php echo esc_attr( $appointment_id ); ?>"
                name="email_body"
                rows="8"
                class="widefat"
                placeholder="Digite a mensagem ou clique em 'Sugerir E-mail com IA'..."
            ></textarea>
        </div>
        
        <div style="margin-top: 10px;">
            <!-- Botão de sugestão de IA -->
            <button 
                type="button"
                class="button dps-ai-suggest-email"
                data-target-subject="#email-subject-<?php echo esc_attr( $appointment_id ); ?>"
                data-target-body="#email-body-<?php echo esc_attr( $appointment_id ); ?>"
                data-type="pos_atendimento"
                data-client-name="<?php echo esc_attr( $client_name ); ?>"
                data-pet-name="<?php echo esc_attr( $pet_name ); ?>"
                data-appointment-date="<?php echo esc_attr( $appointment_date ); ?>"
                data-services='<?php echo esc_attr( wp_json_encode( $services ) ); ?>'
            >
                Sugerir E-mail com IA
            </button>
            
            <!-- Botão de envio (com confirmação) -->
            <button 
                type="button"
                class="button button-primary"
                onclick="confirmAndSendEmail<?php echo esc_js( $appointment_id ); ?>()"
            >
                Enviar E-mail
            </button>
        </div>
    </div>
    
    <script>
    function confirmAndSendEmail<?php echo esc_js( $appointment_id ); ?>() {
        var subject = document.getElementById('email-subject-<?php echo esc_js( $appointment_id ); ?>').value;
        var body = document.getElementById('email-body-<?php echo esc_js( $appointment_id ); ?>').value;
        
        if (!subject || !body) {
            alert('Por favor, preencha o assunto e a mensagem.');
            return;
        }
        
        if (!confirm('Deseja realmente enviar este e-mail para <?php echo esc_js( $client_email ); ?>?')) {
            return;
        }
        
        // Aqui faria a chamada AJAX para enviar o e-mail via wp_mail ou DPS_Communications_API
        // IMPORTANTE: O envio só acontece APÓS confirmação explícita do usuário
        
        jQuery.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'dps_send_email', // Seu handler AJAX
                nonce: '<?php echo esc_js( wp_create_nonce( 'dps_send_email' ) ); ?>',
                to: '<?php echo esc_js( $client_email ); ?>',
                subject: subject,
                body: body,
                appointment_id: <?php echo absint( $appointment_id ); ?>
            },
            success: function(response) {
                if (response.success) {
                    alert('E-mail enviado com sucesso!');
                } else {
                    alert('Erro ao enviar e-mail: ' + (response.data.message || 'Erro desconhecido'));
                }
            },
            error: function() {
                alert('Erro ao enviar e-mail.');
            }
        });
    }
    </script>
    <?php
}

/**
 * EXEMPLO 3: Cobrança Suave via WhatsApp
 *
 * Contexto: Cliente tem pagamento pendente, enviar lembrete educado.
 */
function example_payment_reminder_whatsapp() {
    $client_id    = 789;
    $client_name  = 'Carlos Oliveira';
    $client_phone = '11976543210';
    $amount       = 'R$ 250,00';
    $pet_name     = 'Toby';
    $service_date = '05/12/2024';
    $services     = [ 'Banho', 'Tosa', 'Perfume' ];

    ?>
    <div class="dps-payment-reminder">
        <h3>Lembrete de Pagamento</h3>
        
        <p>
            <strong>Cliente:</strong> <?php echo esc_html( $client_name ); ?><br>
            <strong>Valor pendente:</strong> <?php echo esc_html( $amount ); ?>
        </p>
        
        <label for="payment-whatsapp-message">Mensagem:</label>
        <textarea 
            id="payment-whatsapp-message"
            rows="4"
            class="widefat"
        ></textarea>
        
        <div style="margin-top: 10px;">
            <button 
                type="button"
                class="button dps-ai-suggest-whatsapp"
                data-target="#payment-whatsapp-message"
                data-type="cobranca_suave"
                data-client-name="<?php echo esc_attr( $client_name ); ?>"
                data-pet-name="<?php echo esc_attr( $pet_name ); ?>"
                data-appointment-date="<?php echo esc_attr( $service_date ); ?>"
                data-services='<?php echo esc_attr( wp_json_encode( $services ) ); ?>'
                data-amount="<?php echo esc_attr( $amount ); ?>"
            >
                Sugerir com IA
            </button>
            
            <a 
                href="#"
                class="button button-primary"
                onclick="openWhatsAppWithMessage('<?php echo esc_js( $client_phone ); ?>', document.getElementById('payment-whatsapp-message').value); return false;"
            >
                Abrir WhatsApp
            </a>
        </div>
    </div>
    <?php
}

/**
 * EXEMPLO 4: Uso Programático (sem interface)
 *
 * Contexto: Gerar mensagem em background (ex: para preview de template).
 */
function example_programmatic_usage() {
    // Contexto da mensagem
    $context = [
        'type'              => 'confirmacao',
        'client_name'       => 'Ana Paula',
        'pet_name'          => 'Luna',
        'appointment_date'  => '20/12/2024',
        'appointment_time'  => '10:00',
        'services'          => [ 'Banho', 'Tosa' ],
        'groomer_name'      => 'Fernanda',
    ];

    // Gera sugestão de WhatsApp
    $whatsapp_result = DPS_AI_Message_Assistant::suggest_whatsapp_message( $context );
    
    if ( null !== $whatsapp_result ) {
        $message = $whatsapp_result['text'];
        // Usar $message...
        error_log( 'WhatsApp sugerido: ' . $message );
    } else {
        // IA indisponível, usar mensagem padrão
        $message = sprintf(
            'Olá %s! Seu agendamento para %s está confirmado para %s às %s.',
            $context['client_name'],
            $context['pet_name'],
            $context['appointment_date'],
            $context['appointment_time']
        );
    }

    // Gera sugestão de e-mail
    $email_result = DPS_AI_Message_Assistant::suggest_email_message( $context );
    
    if ( null !== $email_result ) {
        $subject = $email_result['subject'];
        $body    = $email_result['body'];
        // Usar $subject e $body...
        error_log( 'E-mail sugerido - Assunto: ' . $subject );
    } else {
        // IA indisponível, usar template padrão
        $subject = 'Confirmação de Agendamento - Desi Pet Shower';
        $body    = sprintf(
            "Olá %s,\n\nSeu agendamento para %s está confirmado!\n\nData: %s\nHora: %s\nServiços: %s",
            $context['client_name'],
            $context['pet_name'],
            $context['appointment_date'],
            $context['appointment_time'],
            implode( ', ', $context['services'] )
        );
    }

    return [
        'whatsapp' => $message,
        'email'    => [
            'subject' => $subject,
            'body'    => $body,
        ],
    ];
}

/**
 * EXEMPLO 5: Integração com DPS_Communications_API
 *
 * Demonstra como combinar sugestão de IA com envio via API central.
 */
function example_ai_with_communications_api( $appointment_id ) {
    // Busca dados do agendamento
    $client_id   = get_post_meta( $appointment_id, 'dps_client_id', true );
    $client      = get_post( $client_id );
    $client_name = $client ? $client->post_title : '';
    $client_phone = get_post_meta( $client_id, 'client_phone', true );

    // Monta contexto para IA
    $context = [
        'type'              => 'lembrete',
        'client_name'       => $client_name,
        'pet_name'          => 'Rex', // Buscar do agendamento
        'appointment_date'  => '15/12/2024', // Buscar do agendamento
        'appointment_time'  => '14:00', // Buscar do agendamento
        'services'          => [ 'Banho', 'Tosa' ], // Buscar do agendamento
    ];

    // Gera sugestão com IA
    $suggestion = DPS_AI_Message_Assistant::suggest_whatsapp_message( $context );
    
    if ( null !== $suggestion ) {
        $message = $suggestion['text'];
    } else {
        // Fallback para mensagem padrão
        $message = sprintf(
            'Lembrete: Você tem agendamento para %s amanhã às %s.',
            $context['pet_name'],
            $context['appointment_time']
        );
    }

    // IMPORTANTE: Aqui NÃO enviamos automaticamente!
    // Em vez disso, mostraríamos a $message para o usuário revisar.
    // Ou, se for um envio automático de lembrete diário (cron),
    // poderíamos enviar após log apropriado:
    
    // DPS_Communications_API::get_instance()->send_whatsapp(
    //     $client_phone,
    //     $message,
    //     [
    //         'appointment_id' => $appointment_id,
    //         'type'           => 'reminder',
    //         'generated_by'   => 'ai',
    //     ]
    // );
    
    return $message;
}

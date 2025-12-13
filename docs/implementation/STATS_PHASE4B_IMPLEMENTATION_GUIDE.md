# Stats Add-on — Phase 4B Implementation Guide

**Version:** 1.7.0  
**Phase:** F4.2 (Automated Alerts) + F4.3 (Scheduled Reports)  
**Complexity:** High  
**Estimated Effort:** 10-14 hours  
**Dependencies:** Phases 1, 2, 3.1 implemented (v1.4.0+)

---

## Overview

This guide covers implementation of:
- **F4.2:** Automated alerts when KPIs drop below thresholds
- **F4.3:** Scheduled weekly/monthly reports via email

Both features use WP-Cron with manual trigger fallbacks and include anti-spam mechanisms.

---

## F4.2 — Automated Alerts

### Objective

Send email alerts when appointment volume drops significantly compared to previous period.

### Settings (4 new options)

```php
// File: desi-pet-shower-stats-addon.php

function dps_stats_register_alert_settings() {
    register_setting(
        'dps_stats_alerts',
        'dps_stats_alerts_enabled',
        array(
            'type' => 'boolean',
            'default' => false,
            'sanitize_callback' => 'rest_sanitize_boolean',
        )
    );

    register_setting(
        'dps_stats_alerts',
        'dps_stats_alert_drop_threshold_pct',
        array(
            'type' => 'integer',
            'default' => 20,
            'sanitize_callback' => function($value) {
                $int = absint($value);
                return max(5, min(100, $int)); // Between 5% and 100%
            },
        )
    );

    register_setting(
        'dps_stats_alerts',
        'dps_stats_alert_email_to',
        array(
            'type' => 'string',
            'default' => get_option('admin_email'),
            'sanitize_callback' => 'sanitize_email',
        )
    );

    // Store last alert signature to prevent spam
    register_setting(
        'dps_stats_alerts',
        'dps_stats_last_alert_sent',
        array(
            'type' => 'array',
            'default' => array(),
        )
    );
}
add_action('admin_init', 'dps_stats_register_alert_settings');
```

### Settings UI

```php
// File: desi-pet-shower-stats-addon.php

function dps_stats_render_alerts_settings() {
    $enabled = get_option('dps_stats_alerts_enabled', false);
    $threshold = get_option('dps_stats_alert_drop_threshold_pct', 20);
    $email = get_option('dps_stats_alert_email_to', get_option('admin_email'));
    
    ?>
    <div class="wrap">
        <h2><?php esc_html_e('Alertas Automáticos', 'dps-stats'); ?></h2>
        
        <form method="post" action="options.php">
            <?php
            settings_fields('dps_stats_alerts');
            wp_nonce_field('dps_stats_alerts_settings', '_wpnonce_alerts');
            ?>
            
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="dps_stats_alerts_enabled">
                            <?php esc_html_e('Ativar Alertas', 'dps-stats'); ?>
                        </label>
                    </th>
                    <td>
                        <label>
                            <input type="checkbox" 
                                   id="dps_stats_alerts_enabled" 
                                   name="dps_stats_alerts_enabled" 
                                   value="1" 
                                   <?php checked($enabled, true); ?> />
                            <?php esc_html_e('Enviar alertas quando houver queda significativa', 'dps-stats'); ?>
                        </label>
                        <p class="description">
                            <?php esc_html_e('Compara últimos 7 dias com 7 dias anteriores diariamente.', 'dps-stats'); ?>
                        </p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="dps_stats_alert_drop_threshold_pct">
                            <?php esc_html_e('Threshold de Queda (%)', 'dps-stats'); ?>
                        </label>
                    </th>
                    <td>
                        <input type="number" 
                               id="dps_stats_alert_drop_threshold_pct" 
                               name="dps_stats_alert_drop_threshold_pct" 
                               value="<?php echo esc_attr($threshold); ?>" 
                               min="5" 
                               max="100" 
                               step="1" />
                        <p class="description">
                            <?php esc_html_e('Enviar alerta se queda for >= este percentual (5-100%). Padrão: 20%.', 'dps-stats'); ?>
                        </p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="dps_stats_alert_email_to">
                            <?php esc_html_e('Email de Destino', 'dps-stats'); ?>
                        </label>
                    </th>
                    <td>
                        <input type="email" 
                               id="dps_stats_alert_email_to" 
                               name="dps_stats_alert_email_to" 
                               value="<?php echo esc_attr($email); ?>" 
                               class="regular-text" />
                        <p class="description">
                            <?php esc_html_e('Email que receberá os alertas. Padrão: email do administrador.', 'dps-stats'); ?>
                        </p>
                    </td>
                </tr>
            </table>
            
            <p class="submit">
                <button type="submit" class="button button-primary">
                    <?php esc_html_e('Salvar Configurações', 'dps-stats'); ?>
                </button>
                
                <button type="button" 
                        id="dps-stats-test-alert" 
                        class="button button-secondary">
                    <?php esc_html_e('Testar Alerta (enviar email)', 'dps-stats'); ?>
                </button>
            </p>
        </form>
        
        <hr />
        
        <h3><?php esc_html_e('Último Alerta Enviado', 'dps-stats'); ?></h3>
        <?php
        $last_alert = get_option('dps_stats_last_alert_sent', array());
        if (!empty($last_alert['timestamp'])) {
            echo '<p>';
            printf(
                esc_html__('Data: %s', 'dps-stats'),
                '<strong>' . esc_html(date_i18n(get_option('date_format') . ' ' . get_option('time_format'), $last_alert['timestamp'])) . '</strong>'
            );
            echo '<br />';
            printf(
                esc_html__('Motivo: Queda de %s%% nos atendimentos (%d → %d)', 'dps-stats'),
                '<strong>' . esc_html($last_alert['drop_pct'] ?? 0) . '</strong>',
                absint($last_alert['prev_count'] ?? 0),
                absint($last_alert['current_count'] ?? 0)
            );
            echo '</p>';
        } else {
            echo '<p>' . esc_html__('Nenhum alerta enviado ainda.', 'dps-stats') . '</p>';
        }
        ?>
    </div>
    
    <script>
    jQuery(document).ready(function($) {
        $('#dps-stats-test-alert').on('click', function(e) {
            e.preventDefault();
            if (!confirm('<?php echo esc_js(__('Enviar email de teste?', 'dps-stats')); ?>')) {
                return;
            }
            
            var $btn = $(this);
            $btn.prop('disabled', true).text('<?php echo esc_js(__('Enviando...', 'dps-stats')); ?>');
            
            $.post(ajaxurl, {
                action: 'dps_stats_test_alert',
                _wpnonce: '<?php echo wp_create_nonce('dps_stats_test_alert'); ?>'
            }, function(response) {
                if (response.success) {
                    alert('<?php echo esc_js(__('Email enviado com sucesso!', 'dps-stats')); ?>');
                } else {
                    alert('<?php echo esc_js(__('Erro ao enviar email.', 'dps-stats')); ?>');
                }
                $btn.prop('disabled', false).text('<?php echo esc_js(__('Testar Alerta (enviar email)', 'dps-stats')); ?>');
            });
        });
    });
    </script>
    <?php
}
```

### Cron Setup

```php
// File: desi-pet-shower-stats-addon.php

function dps_stats_schedule_alerts_cron() {
    if (!wp_next_scheduled('dps_stats_alerts_cron')) {
        wp_schedule_event(time(), 'daily', 'dps_stats_alerts_cron');
    }
}
add_action('wp', 'dps_stats_schedule_alerts_cron');

function dps_stats_clear_alerts_cron() {
    wp_clear_scheduled_hook('dps_stats_alerts_cron');
}
register_deactivation_hook(__FILE__, 'dps_stats_clear_alerts_cron');
```

### Alert Logic

```php
// File: desi-pet-shower-stats-addon.php

function dps_stats_process_alerts() {
    // Check if alerts are enabled
    $enabled = get_option('dps_stats_alerts_enabled', false);
    if (!$enabled) {
        return;
    }
    
    $threshold = absint(get_option('dps_stats_alert_drop_threshold_pct', 20));
    
    // Calculate current period (last 7 days)
    $current_end = date('Y-m-d');
    $current_start = date('Y-m-d', strtotime('-7 days'));
    
    // Calculate previous period (8-14 days ago)
    $prev_end = date('Y-m-d', strtotime('-8 days'));
    $prev_start = date('Y-m-d', strtotime('-14 days'));
    
    // Get appointment counts
    $current_count = DPS_Stats_API::get_appointments_count($current_start, $current_end);
    $prev_count = DPS_Stats_API::get_appointments_count($prev_start, $prev_end);
    
    // Calculate drop percentage
    if ($prev_count == 0) {
        // Can't calculate drop if previous was zero
        return;
    }
    
    $drop_pct = round((($prev_count - $current_count) / $prev_count) * 100, 1);
    
    // Check if drop exceeds threshold
    if ($drop_pct < $threshold) {
        // No significant drop
        return;
    }
    
    // Anti-spam check
    $signature = md5($current_start . $current_end . $threshold . $drop_pct);
    $last_alert = get_option('dps_stats_last_alert_sent', array());
    
    if (!empty($last_alert['signature']) && $last_alert['signature'] === $signature) {
        $time_since = time() - $last_alert['timestamp'];
        if ($time_since < DAY_IN_SECONDS) {
            // Already sent this alert in last 24h
            return;
        }
    }
    
    // Send alert email
    $sent = dps_stats_send_alert_email($drop_pct, $prev_count, $current_count, $current_start, $current_end);
    
    if ($sent) {
        // Store alert signature
        update_option('dps_stats_last_alert_sent', array(
            'timestamp' => time(),
            'signature' => $signature,
            'drop_pct' => $drop_pct,
            'prev_count' => $prev_count,
            'current_count' => $current_count,
        ));
    }
}
add_action('dps_stats_alerts_cron', 'dps_stats_process_alerts');
```

### Email Template (Alert)

```php
// File: desi-pet-shower-stats-addon.php

function dps_stats_send_alert_email($drop_pct, $prev_count, $current_count, $period_start, $period_end) {
    $to = sanitize_email(get_option('dps_stats_alert_email_to', get_option('admin_email')));
    
    if (!is_email($to)) {
        return false;
    }
    
    $subject = sprintf(
        __('⚠️ Alerta DPS Stats: Queda de %s%% nos atendimentos', 'dps-stats'),
        $drop_pct
    );
    
    $stats_url = admin_url('admin.php?page=desi-pet-shower');
    
    $message = sprintf(
        __("Olá,\n\nDetectamos uma queda significativa no número de atendimentos:\n\n" .
           "📊 Comparação:\n" .
           "• 7 dias anteriores: %d atendimentos\n" .
           "• Últimos 7 dias: %d atendimentos\n" .
           "• Queda: %s%%\n\n" .
           "📅 Período analisado: %s a %s\n\n" .
           "💡 Sugestões:\n" .
           "• Verifique se houve feriados ou eventos sazonais\n" .
           "• Revise campanhas de marketing ativas\n" .
           "• Analise taxa de no-show e cancelamentos\n\n" .
           "🔗 Ver dashboard: %s\n\n" .
           "Este é um alerta automático do DPS Stats Add-on.\n" .
           "Para desativar, acesse Configurações > Stats > Alertas.",
           'dps-stats'),
        $prev_count,
        $current_count,
        $drop_pct,
        date_i18n(get_option('date_format'), strtotime($period_start)),
        date_i18n(get_option('date_format'), strtotime($period_end)),
        $stats_url
    );
    
    $headers = array('Content-Type: text/plain; charset=UTF-8');
    
    return wp_mail($to, $subject, $message, $headers);
}
```

### AJAX Handler (Test Alert)

```php
// File: desi-pet-shower-stats-addon.php

function dps_stats_ajax_test_alert() {
    check_ajax_referer('dps_stats_test_alert', '_wpnonce');
    
    if (!current_user_can('manage_options')) {
        wp_send_json_error(array('message' => __('Permissão negada.', 'dps-stats')));
    }
    
    // Send test email with dummy data
    $sent = dps_stats_send_alert_email(25.5, 150, 112, date('Y-m-d', strtotime('-7 days')), date('Y-m-d'));
    
    if ($sent) {
        wp_send_json_success(array('message' => __('Email enviado com sucesso!', 'dps-stats')));
    } else {
        wp_send_json_error(array('message' => __('Erro ao enviar email.', 'dps-stats')));
    }
}
add_action('wp_ajax_dps_stats_test_alert', 'dps_stats_ajax_test_alert');
```

---

## F4.3 — Scheduled Reports

### Objective

Send weekly or monthly summary reports via email with key performance indicators.

### Settings (3 new options)

```php
// File: desi-pet-shower-stats-addon.php

function dps_stats_register_report_settings() {
    register_setting(
        'dps_stats_reports',
        'dps_stats_reports_enabled',
        array(
            'type' => 'boolean',
            'default' => false,
            'sanitize_callback' => 'rest_sanitize_boolean',
        )
    );

    register_setting(
        'dps_stats_reports',
        'dps_stats_reports_frequency',
        array(
            'type' => 'string',
            'default' => 'weekly',
            'sanitize_callback' => function($value) {
                return in_array($value, array('weekly', 'monthly'), true) ? $value : 'weekly';
            },
        )
    );

    register_setting(
        'dps_stats_reports',
        'dps_stats_reports_email_to',
        array(
            'type' => 'string',
            'default' => get_option('admin_email'),
            'sanitize_callback' => 'sanitize_email',
        )
    );
}
add_action('admin_init', 'dps_stats_register_report_settings');
```

### Settings UI

```php
// File: desi-pet-shower-stats-addon.php

function dps_stats_render_reports_settings() {
    $enabled = get_option('dps_stats_reports_enabled', false);
    $frequency = get_option('dps_stats_reports_frequency', 'weekly');
    $email = get_option('dps_stats_reports_email_to', get_option('admin_email'));
    
    ?>
    <div class="wrap">
        <h2><?php esc_html_e('Relatórios Agendados', 'dps-stats'); ?></h2>
        
        <form method="post" action="options.php">
            <?php
            settings_fields('dps_stats_reports');
            wp_nonce_field('dps_stats_reports_settings', '_wpnonce_reports');
            ?>
            
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="dps_stats_reports_enabled">
                            <?php esc_html_e('Ativar Relatórios', 'dps-stats'); ?>
                        </label>
                    </th>
                    <td>
                        <label>
                            <input type="checkbox" 
                                   id="dps_stats_reports_enabled" 
                                   name="dps_stats_reports_enabled" 
                                   value="1" 
                                   <?php checked($enabled, true); ?> />
                            <?php esc_html_e('Enviar relatórios automaticamente por email', 'dps-stats'); ?>
                        </label>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="dps_stats_reports_frequency">
                            <?php esc_html_e('Frequência', 'dps-stats'); ?>
                        </label>
                    </th>
                    <td>
                        <select id="dps_stats_reports_frequency" name="dps_stats_reports_frequency">
                            <option value="weekly" <?php selected($frequency, 'weekly'); ?>>
                                <?php esc_html_e('Semanal (toda segunda-feira 08:00)', 'dps-stats'); ?>
                            </option>
                            <option value="monthly" <?php selected($frequency, 'monthly'); ?>>
                                <?php esc_html_e('Mensal (todo dia 1 às 08:00)', 'dps-stats'); ?>
                            </option>
                        </select>
                        <p class="description">
                            <?php esc_html_e('Define quando os relatórios serão enviados automaticamente.', 'dps-stats'); ?>
                        </p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="dps_stats_reports_email_to">
                            <?php esc_html_e('Email de Destino', 'dps-stats'); ?>
                        </label>
                    </th>
                    <td>
                        <input type="email" 
                               id="dps_stats_reports_email_to" 
                               name="dps_stats_reports_email_to" 
                               value="<?php echo esc_attr($email); ?>" 
                               class="regular-text" />
                        <p class="description">
                            <?php esc_html_e('Email que receberá os relatórios. Padrão: email do administrador.', 'dps-stats'); ?>
                        </p>
                    </td>
                </tr>
            </table>
            
            <p class="submit">
                <button type="submit" class="button button-primary">
                    <?php esc_html_e('Salvar Configurações', 'dps-stats'); ?>
                </button>
                
                <button type="button" 
                        id="dps-stats-send-report-now" 
                        class="button button-secondary">
                    <?php esc_html_e('Enviar Relatório Agora', 'dps-stats'); ?>
                </button>
            </p>
        </form>
        
        <hr />
        
        <h3><?php esc_html_e('Próximo Envio Agendado', 'dps-stats'); ?></h3>
        <?php
        $next_run = wp_next_scheduled('dps_stats_reports_cron');
        if ($next_run) {
            echo '<p>';
            printf(
                esc_html__('Data/hora: %s', 'dps-stats'),
                '<strong>' . esc_html(date_i18n(get_option('date_format') . ' ' . get_option('time_format'), $next_run)) . '</strong>'
            );
            echo '</p>';
        } else {
            echo '<p>' . esc_html__('Nenhum relatório agendado. Ative a opção acima.', 'dps-stats') . '</p>';
        }
        ?>
    </div>
    
    <script>
    jQuery(document).ready(function($) {
        $('#dps-stats-send-report-now').on('click', function(e) {
            e.preventDefault();
            if (!confirm('<?php echo esc_js(__('Enviar relatório agora (período: última semana/mês)?', 'dps-stats')); ?>')) {
                return;
            }
            
            var $btn = $(this);
            $btn.prop('disabled', true).text('<?php echo esc_js(__('Enviando...', 'dps-stats')); ?>');
            
            $.post(ajaxurl, {
                action: 'dps_stats_send_report_now',
                _wpnonce: '<?php echo wp_create_nonce('dps_stats_send_report_now'); ?>'
            }, function(response) {
                if (response.success) {
                    alert('<?php echo esc_js(__('Relatório enviado com sucesso!', 'dps-stats')); ?>');
                } else {
                    alert('<?php echo esc_js(__('Erro ao enviar relatório.', 'dps-stats')); ?>');
                }
                $btn.prop('disabled', false).text('<?php echo esc_js(__('Enviar Relatório Agora', 'dps-stats')); ?>');
            });
        });
    });
    </script>
    <?php
}
```

### Cron Setup (Dynamic)

```php
// File: desi-pet-shower-stats-addon.php

function dps_stats_schedule_reports_cron() {
    $enabled = get_option('dps_stats_reports_enabled', false);
    $frequency = get_option('dps_stats_reports_frequency', 'weekly');
    
    // Clear existing schedule
    wp_clear_scheduled_hook('dps_stats_reports_cron');
    
    if (!$enabled) {
        return;
    }
    
    // Schedule based on frequency
    if ($frequency === 'weekly') {
        // Every Monday at 08:00
        $next_monday = strtotime('next monday 08:00');
        wp_schedule_event($next_monday, 'weekly', 'dps_stats_reports_cron');
    } elseif ($frequency === 'monthly') {
        // First day of next month at 08:00
        $next_month = strtotime('first day of next month 08:00');
        wp_schedule_event($next_month, 'monthly', 'dps_stats_reports_cron');
    }
}
add_action('update_option_dps_stats_reports_enabled', 'dps_stats_schedule_reports_cron');
add_action('update_option_dps_stats_reports_frequency', 'dps_stats_schedule_reports_cron');
add_action('wp', 'dps_stats_schedule_reports_cron');
```

### Report Generation Logic

```php
// File: desi-pet-shower-stats-addon.php

function dps_stats_process_reports() {
    $enabled = get_option('dps_stats_reports_enabled', false);
    if (!$enabled) {
        return;
    }
    
    $frequency = get_option('dps_stats_reports_frequency', 'weekly');
    
    // Determine period
    if ($frequency === 'weekly') {
        // Last week (Monday to Sunday)
        $end = date('Y-m-d', strtotime('last sunday'));
        $start = date('Y-m-d', strtotime('last monday', strtotime($end)));
        $period_label = __('Semana', 'dps-stats') . ': ' . date_i18n(get_option('date_format'), strtotime($start)) . ' - ' . date_i18n(get_option('date_format'), strtotime($end));
    } else {
        // Last month
        $start = date('Y-m-01', strtotime('first day of last month'));
        $end = date('Y-m-t', strtotime('last day of last month'));
        $period_label = __('Mês', 'dps-stats') . ': ' . date_i18n('F/Y', strtotime($start));
    }
    
    // Collect metrics
    $metrics = array(
        'period' => $period_label,
        'appointments' => DPS_Stats_API::get_appointments_count($start, $end),
        'cancellations' => DPS_Stats_API::get_cancellations_count($start, $end),
        'new_clients' => DPS_Stats_API::get_new_clients_count($start, $end),
    );
    
    // Financial metrics (if Finance addon active)
    if (dps_stats_table_exists('dps_transacoes')) {
        $financial = DPS_Stats_API::get_financial_totals($start, $end);
        $metrics['revenue'] = $financial['revenue'];
        $metrics['ticket_average'] = $financial['ticket_average'];
    } else {
        $metrics['revenue'] = null;
        $metrics['ticket_average'] = null;
    }
    
    // Advanced KPIs (if Phase 3.1 implemented)
    if (method_exists('DPS_Stats_API', 'get_no_show_rate')) {
        $no_show = DPS_Stats_API::get_no_show_rate($start, $end);
        $metrics['no_show_rate'] = $no_show['value'];
        $metrics['no_show_count'] = $no_show['count'];
    }
    
    if (method_exists('DPS_Stats_API', 'get_return_rate')) {
        $return = DPS_Stats_API::get_return_rate($start, $end, 30);
        $metrics['return_rate'] = $return['value'];
    }
    
    // Send email
    dps_stats_send_report_email($metrics);
}
add_action('dps_stats_reports_cron', 'dps_stats_process_reports');
```

### Email Template (Report)

```php
// File: desi-pet-shower-stats-addon.php

function dps_stats_send_report_email($metrics) {
    $to = sanitize_email(get_option('dps_stats_reports_email_to', get_option('admin_email')));
    
    if (!is_email($to)) {
        return false;
    }
    
    $subject = sprintf(
        __('📊 Relatório DPS Stats: %s', 'dps-stats'),
        $metrics['period']
    );
    
    $stats_url = admin_url('admin.php?page=desi-pet-shower&tab=stats');
    
    // Build message
    $message = sprintf(__("Olá,\n\nSegue o relatório de desempenho do período:\n\n📅 Período: %s\n\n", 'dps-stats'), $metrics['period']);
    
    $message .= __("📊 MÉTRICAS PRINCIPAIS\n\n", 'dps-stats');
    
    $message .= sprintf(__("• Atendimentos: %d\n", 'dps-stats'), $metrics['appointments']);
    $message .= sprintf(__("• Cancelamentos: %d\n", 'dps-stats'), $metrics['cancellations']);
    $message .= sprintf(__("• Novos Clientes: %d\n", 'dps-stats'), $metrics['new_clients']);
    
    if ($metrics['revenue'] !== null) {
        $message .= sprintf(__("• Receita: R$ %s\n", 'dps-stats'), number_format($metrics['revenue'], 2, ',', '.'));
        $message .= sprintf(__("• Ticket Médio: R$ %s\n", 'dps-stats'), number_format($metrics['ticket_average'], 2, ',', '.'));
    }
    
    if (isset($metrics['no_show_rate'])) {
        $message .= sprintf(__("\n• No-Show: %s%% (%d atendimentos)\n", 'dps-stats'), $metrics['no_show_rate'], $metrics['no_show_count']);
    }
    
    if (isset($metrics['return_rate'])) {
        $message .= sprintf(__("• Taxa de Retorno (30d): %s%%\n", 'dps-stats'), $metrics['return_rate']);
    }
    
    $message .= sprintf(__("\n\n🔗 Ver detalhes no dashboard: %s\n\n", 'dps-stats'), $stats_url);
    
    $message .= __("Este é um relatório automático do DPS Stats Add-on.\n" .
                   "Para alterar frequência ou desativar, acesse Configurações > Stats > Relatórios.", 'dps-stats');
    
    $headers = array('Content-Type: text/plain; charset=UTF-8');
    
    return wp_mail($to, $subject, $message, $headers);
}
```

### AJAX Handler (Send Report Now)

```php
// File: desi-pet-shower-stats-addon.php

function dps_stats_ajax_send_report_now() {
    check_ajax_referer('dps_stats_send_report_now', '_wpnonce');
    
    if (!current_user_can('manage_options')) {
        wp_send_json_error(array('message' => __('Permissão negada.', 'dps-stats')));
    }
    
    // Trigger report generation manually
    dps_stats_process_reports();
    
    wp_send_json_success(array('message' => __('Relatório enviado com sucesso!', 'dps-stats')));
}
add_action('wp_ajax_dps_stats_send_report_now', 'dps_stats_ajax_send_report_now');
```

---

## Integration Points

### Settings Page Tab

Add new tab in Stats settings page:

```php
// File: desi-pet-shower-stats-addon.php

function dps_stats_admin_menu() {
    add_submenu_page(
        'desi-pet-shower',
        __('Configurações do Stats', 'dps-stats'),
        __('Stats', 'dps-stats'),
        'manage_options',
        'dps-stats-settings',
        'dps_stats_render_settings_page'
    );
}
add_action('admin_menu', 'dps_stats_admin_menu');

function dps_stats_render_settings_page() {
    $active_tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'alerts';
    
    ?>
    <div class="wrap">
        <h1><?php esc_html_e('Configurações do Stats Add-on', 'dps-stats'); ?></h1>
        
        <h2 class="nav-tab-wrapper">
            <a href="?page=dps-stats-settings&tab=alerts" 
               class="nav-tab <?php echo $active_tab === 'alerts' ? 'nav-tab-active' : ''; ?>">
                <?php esc_html_e('Alertas', 'dps-stats'); ?>
            </a>
            <a href="?page=dps-stats-settings&tab=reports" 
               class="nav-tab <?php echo $active_tab === 'reports' ? 'nav-tab-active' : ''; ?>">
                <?php esc_html_e('Relatórios', 'dps-stats'); ?>
            </a>
        </h2>
        
        <div class="tab-content">
            <?php
            if ($active_tab === 'alerts') {
                dps_stats_render_alerts_settings();
            } elseif ($active_tab === 'reports') {
                dps_stats_render_reports_settings();
            }
            ?>
        </div>
    </div>
    <?php
}
```

---

## Testing Checklist

### F4.2 — Alerts

- [ ] Settings page loads without errors
- [ ] Can enable/disable alerts
- [ ] Threshold slider accepts values 5-100%
- [ ] Email field validates properly
- [ ] "Test Alert" button sends email successfully
- [ ] Received email has correct format and links
- [ ] Cron event is scheduled (`wp_next_scheduled('dps_stats_alerts_cron')`)
- [ ] Alert triggers when drop >= threshold (simulate by adjusting dates)
- [ ] Anti-spam works (doesn't send duplicate in 24h)
- [ ] Last alert info displays correctly on settings page

### F4.3 — Reports

- [ ] Can enable/disable reports
- [ ] Frequency dropdown has weekly/monthly options
- [ ] Email field validates properly
- [ ] "Send Report Now" button sends email successfully
- [ ] Received email has all KPIs formatted correctly
- [ ] Cron event is scheduled (`wp_next_scheduled('dps_stats_reports_cron')`)
- [ ] Weekly schedule: runs on Monday 08:00
- [ ] Monthly schedule: runs on 1st day 08:00
- [ ] Finance metrics show correctly (or N/A if Finance inactive)
- [ ] Links in email work (admin dashboard)

### Regression

- [ ] All Phase 1-3 features still work
- [ ] Dashboard loads without errors
- [ ] Cron doesn't impact site performance
- [ ] Deactivating plugin clears cron events

---

## Notes

### WP-Cron Limitations

- Requires site traffic to trigger (not true cron)
- Consider server cron job calling `wp-cron.php` for reliability
- Add note in settings UI about WP-Cron behavior

### Email Deliverability

- Test with SMTP plugin (WP Mail SMTP, Easy WP SMTP)
- Recommend using transactional email service (SendGrid, Mailgun)
- Add "From" header with site name for better deliverability

### Localization

- All strings use `__()` or `esc_html__()` with `'dps-stats'` text domain
- Date formats use `date_i18n()` for WordPress locale support

---

## Estimated Effort

**Total:** 10-14 hours

**Breakdown:**
- F4.2 Settings + UI: 2h
- F4.2 Alert logic + email: 2-3h
- F4.2 Cron + anti-spam: 1h
- F4.3 Settings + UI: 2h
- F4.3 Report generation: 2-3h
- F4.3 Cron scheduling: 1h
- Testing + debugging: 2h

---

## Security Considerations

- ✅ All AJAX endpoints check nonce + capability
- ✅ Email addresses validated with `sanitize_email()` + `is_email()`
- ✅ Threshold sanitized with `absint()` + min/max bounds
- ✅ Frequency enum validated (only 'weekly'/'monthly')
- ✅ No user input directly in email body (all escaped)
- ✅ Admin-only access (`manage_options`)

---

## Next Steps

1. Implement code following this guide (~10-14h)
2. Test thoroughly (checklist above)
3. Configure SMTP for reliable email delivery
4. Monitor cron execution (`wp cron event list`)
5. Update CHANGELOG.md (v1.7.0)
6. Create PR with incremental commits

**Roadmap after Phase 4B:** Phase 4D (REST API) = Final Stats Add-on 2.0 release 🎉

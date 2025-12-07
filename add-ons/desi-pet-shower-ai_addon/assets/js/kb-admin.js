/**
 * JavaScript para página administrativa da Base de Conhecimento.
 *
 * Gerencia edição rápida de keywords e prioridades via AJAX.
 *
 * @package DPS_AI_Addon
 * @since 1.6.2
 */

(function($) {
	'use strict';

	/**
	 * Inicializa a interface de edição rápida.
	 */
	function init() {
		// Botão de editar
		$('.dps-ai-kb-edit-btn').on('click', function(e) {
			e.preventDefault();
			var $row = $(this).closest('tr');
			enterEditMode($row);
		});

		// Botão de salvar
		$('.dps-ai-kb-save-btn').on('click', function(e) {
			e.preventDefault();
			var $row = $(this).closest('tr');
			saveChanges($row);
		});

		// Botão de cancelar
		$('.dps-ai-kb-cancel-btn').on('click', function(e) {
			e.preventDefault();
			var $row = $(this).closest('tr');
			exitEditMode($row);
		});
	}

	/**
	 * Entra no modo de edição para uma linha.
	 *
	 * @param {jQuery} $row Linha da tabela.
	 */
	function enterEditMode($row) {
		// Esconde displays e mostra inputs
		$row.find('.dps-ai-kb-display').hide();
		$row.find('.dps-ai-kb-edit').show();

		// Esconde botão editar, mostra salvar/cancelar
		$row.find('.dps-ai-kb-edit-btn').hide();
		$row.find('.dps-ai-kb-save-btn, .dps-ai-kb-cancel-btn').show();

		// Foco no primeiro campo
		$row.find('.dps-ai-kb-edit textarea').first().focus();
	}

	/**
	 * Sai do modo de edição sem salvar.
	 *
	 * @param {jQuery} $row Linha da tabela.
	 */
	function exitEditMode($row) {
		// Mostra displays e esconde inputs
		$row.find('.dps-ai-kb-display').show();
		$row.find('.dps-ai-kb-edit').hide();

		// Mostra botão editar, esconde salvar/cancelar
		$row.find('.dps-ai-kb-edit-btn').show();
		$row.find('.dps-ai-kb-save-btn, .dps-ai-kb-cancel-btn').hide();

		// Restaura valores originais
		var $keywordsDisplay = $row.find('.dps-ai-kb-keywords-cell .dps-ai-kb-display code');
		var $priorityDisplay = $row.find('.dps-ai-kb-priority-cell .dps-ai-kb-display strong');
		
		var originalKeywords = $keywordsDisplay.text();
		var originalPriority = $priorityDisplay.text();

		$row.find('.dps-ai-kb-keywords-cell textarea').val(originalKeywords);
		$row.find('.dps-ai-kb-priority-cell input').val(originalPriority);
	}

	/**
	 * Salva as alterações via AJAX.
	 *
	 * @param {jQuery} $row Linha da tabela.
	 */
	function saveChanges($row) {
		var postId = $row.data('post-id');
		var keywords = $row.find('.dps-ai-kb-keywords-cell textarea').val();
		var priority = $row.find('.dps-ai-kb-priority-cell input').val();

		// Valida prioridade
		priority = parseInt(priority, 10);
		if (isNaN(priority) || priority < 1 || priority > 10) {
			alert(dpsAiKbAdmin.strings.error + ': ' + 'Prioridade deve ser entre 1 e 10');
			return;
		}

		// Desabilita botões
		$row.addClass('dps-ai-kb-saving');
		$row.find('.dps-ai-kb-save-btn').text(dpsAiKbAdmin.strings.saving).prop('disabled', true);
		$row.find('.dps-ai-kb-cancel-btn').prop('disabled', true);

		// Envia requisição AJAX
		$.ajax({
			url: dpsAiKbAdmin.ajaxUrl,
			type: 'POST',
			data: {
				action: 'dps_ai_kb_quick_edit',
				nonce: dpsAiKbAdmin.nonce,
				post_id: postId,
				keywords: keywords,
				priority: priority
			},
			success: function(response) {
				if (response.success) {
					// Atualiza displays
					updateDisplays($row, response.data.keywords, response.data.priority);
					
					// Sai do modo de edição
					exitEditMode($row);
					
					// Feedback visual
					$row.addClass('dps-ai-kb-flash');
					setTimeout(function() {
						$row.removeClass('dps-ai-kb-flash');
					}, 600);
					
					// Mostra mensagem temporária
					showNotice('success', dpsAiKbAdmin.strings.saved);
				} else {
					alert(dpsAiKbAdmin.strings.error + ': ' + (response.data.message || 'Erro desconhecido'));
				}
			},
			error: function() {
				alert(dpsAiKbAdmin.strings.error);
			},
			complete: function() {
				// Reabilita botões
				$row.removeClass('dps-ai-kb-saving');
				$row.find('.dps-ai-kb-save-btn').text('Salvar').prop('disabled', false);
				$row.find('.dps-ai-kb-cancel-btn').prop('disabled', false);
			}
		});
	}

	/**
	 * Atualiza os displays com novos valores.
	 *
	 * @param {jQuery} $row      Linha da tabela.
	 * @param {string} keywords  Novas keywords.
	 * @param {number} priority  Nova prioridade.
	 */
	function updateDisplays($row, keywords, priority) {
		// Atualiza keywords
		var $keywordsDisplay = $row.find('.dps-ai-kb-keywords-cell .dps-ai-kb-display');
		if (keywords) {
			$keywordsDisplay.html('<code>' + escapeHtml(keywords) + '</code>');
		} else {
			$keywordsDisplay.html('<em>Nenhuma keyword definida</em>');
		}

		// Atualiza prioridade
		var $priorityDisplay = $row.find('.dps-ai-kb-priority-cell .dps-ai-kb-display');
		var badgeClass = 'dps-ai-badge-medium';
		var badgeText = 'Média';
		
		if (priority >= 8) {
			badgeClass = 'dps-ai-badge-high';
			badgeText = 'Alta';
		} else if (priority < 4) {
			badgeClass = 'dps-ai-badge-low';
			badgeText = 'Baixa';
		}

		$priorityDisplay.html(
			'<strong>' + priority + '</strong><br />' +
			'<span class="dps-ai-badge ' + badgeClass + '">' + badgeText + '</span>'
		);
	}

	/**
	 * Mostra notice temporária.
	 *
	 * @param {string} type    Tipo da notice (success, error, warning).
	 * @param {string} message Mensagem a exibir.
	 */
	function showNotice(type, message) {
		var $notice = $('<div class="notice notice-' + type + ' is-dismissible"><p>' + message + '</p></div>');
		$('.wrap h1').after($notice);
		
		setTimeout(function() {
			$notice.fadeOut(function() {
				$(this).remove();
			});
		}, 3000);
	}

	/**
	 * Escapa HTML para prevenir XSS.
	 *
	 * @param {string} text Texto a escapar.
	 * @return {string} Texto escapado.
	 */
	function escapeHtml(text) {
		var map = {
			'&': '&amp;',
			'<': '&lt;',
			'>': '&gt;',
			'"': '&quot;',
			"'": '&#039;'
		};
		return text.replace(/[&<>"']/g, function(m) { return map[m]; });
	}

	// Inicializa quando DOM estiver pronto
	$(document).ready(init);

})(jQuery);

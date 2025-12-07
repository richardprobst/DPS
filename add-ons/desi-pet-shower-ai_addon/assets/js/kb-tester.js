/**
 * JavaScript para página de teste da Base de Conhecimento.
 *
 * Gerencia teste de matching e exibição de resultados.
 *
 * @package DPS_AI_Addon
 * @since 1.6.2
 */

(function($) {
	'use strict';

	/**
	 * Inicializa a interface de teste.
	 */
	function init() {
		$('#dps-ai-test-btn').on('click', function(e) {
			e.preventDefault();
			runTest();
		});

		// Permite testar pressionando Enter no textarea
		$('#dps-ai-test-question').on('keypress', function(e) {
			if (e.which === 13 && e.ctrlKey) {
				e.preventDefault();
				runTest();
			}
		});
	}

	/**
	 * Executa o teste de matching.
	 */
	function runTest() {
		var question = $('#dps-ai-test-question').val().trim();
		var limit = parseInt($('#dps-ai-test-limit').val(), 10) || 5;

		if (!question) {
			alert('Por favor, digite uma pergunta de teste.');
			return;
		}

		// Mostra loading
		showLoading();

		// Envia requisição AJAX
		$.ajax({
			url: dpsAiKbTester.ajaxUrl,
			type: 'POST',
			data: {
				action: 'dps_ai_kb_test_matching',
				nonce: dpsAiKbTester.nonce,
				question: question,
				limit: limit
			},
			success: function(response) {
				if (response.success) {
					displayResults(response.data, question);
				} else {
					alert(dpsAiKbTester.strings.error + ': ' + (response.data.message || 'Erro desconhecido'));
					hideResults();
				}
			},
			error: function() {
				alert(dpsAiKbTester.strings.error);
				hideResults();
			}
		});
	}

	/**
	 * Mostra estado de loading.
	 */
	function showLoading() {
		var $results = $('#dps-ai-test-results');
		var $content = $('#dps-ai-test-results-content');

		$content.html(
			'<div class="dps-ai-test-loading">' +
			'<span class="spinner is-active"></span>' +
			'<p>' + dpsAiKbTester.strings.testing + '</p>' +
			'</div>'
		);

		$results.show();
	}

	/**
	 * Esconde resultados.
	 */
	function hideResults() {
		$('#dps-ai-test-results').hide();
	}

	/**
	 * Exibe os resultados do teste.
	 *
	 * @param {Object} data     Dados retornados pelo servidor.
	 * @param {string} question Pergunta testada.
	 */
	function displayResults(data, question) {
		var $content = $('#dps-ai-test-results-content');
		var html = '';

		// Resumo
		html += '<div class="dps-ai-test-summary">';
		html += '<h3>Resumo</h3>';
		html += '<div class="dps-ai-test-summary-stats">';
		
		html += '<div class="dps-ai-test-summary-stat">';
		html += '<span class="label">Artigos Encontrados</span>';
		html += '<span class="value">' + data.count + '</span>';
		html += '</div>';
		
		html += '<div class="dps-ai-test-summary-stat">';
		html += '<span class="label">Total de Caracteres</span>';
		html += '<span class="value">' + formatNumber(data.total_chars) + '</span>';
		html += '</div>';
		
		html += '<div class="dps-ai-test-summary-stat">';
		html += '<span class="label">Tokens Estimados</span>';
		html += '<span class="value">~' + formatNumber(data.total_tokens) + '</span>';
		html += '</div>';
		
		html += '</div>';
		html += '</div>';

		// Artigos
		if (data.articles && data.articles.length > 0) {
			data.articles.forEach(function(article, index) {
				html += renderArticle(article, index + 1);
			});
		} else {
			html += '<div class="dps-ai-test-no-results">';
			html += '<p>Nenhum artigo encontrado para esta pergunta. Verifique as keywords dos artigos da base.</p>';
			html += '</div>';
		}

		$content.html(html);
	}

	/**
	 * Renderiza um artigo.
	 *
	 * @param {Object} article Dados do artigo.
	 * @param {number} index   Posição na lista.
	 * @return {string} HTML do artigo.
	 */
	function renderArticle(article, index) {
		var html = '';
		
		html += '<div class="dps-ai-test-article">';
		
		// Header
		html += '<div class="dps-ai-test-article-header">';
		html += '<div class="dps-ai-test-article-title">';
		html += '<h3>' + index + '. ';
		html += '<a href="post.php?post=' + article.id + '&action=edit" target="_blank">';
		html += escapeHtml(article.title);
		html += '</a>';
		html += '</h3>';
		html += '</div>';
		
		html += '<div class="dps-ai-test-article-badges">';
		html += getBadgePriority(article.priority);
		html += getBadgeSize(article.size.classification, article.size.label);
		html += '</div>';
		
		html += '</div>';
		
		// Content
		html += '<div class="dps-ai-test-article-content">';
		
		// Prioridade
		html += '<div class="dps-ai-test-article-row">';
		html += '<strong>Prioridade:</strong> ' + article.priority + '/10';
		html += '</div>';
		
		// Keywords
		html += '<div class="dps-ai-test-article-row">';
		html += '<strong>Keywords:</strong> ';
		html += '<div class="dps-ai-test-article-keywords">';
		
		var allKeywords = article.keywords.split(',').map(function(k) { return k.trim(); });
		allKeywords.forEach(function(keyword) {
			var isMatched = article.matched_keywords.indexOf(keyword.toLowerCase()) !== -1;
			var cssClass = isMatched ? 'matched' : '';
			html += '<span class="dps-ai-test-article-keyword ' + cssClass + '">' + escapeHtml(keyword) + '</span>';
		});
		
		html += '</div>';
		html += '</div>';
		
		// Tamanho
		html += '<div class="dps-ai-test-article-row">';
		html += '<strong>Tamanho:</strong> ';
		html += formatNumber(article.size.chars) + ' caracteres, ';
		html += formatNumber(article.size.words) + ' palavras, ';
		html += '~' + formatNumber(article.size.tokens_estimate) + ' tokens';
		html += '</div>';
		
		// Trecho
		html += '<div class="dps-ai-test-article-excerpt">';
		html += '<strong>Trecho:</strong><br>';
		html += escapeHtml(article.excerpt);
		html += '</div>';
		
		html += '</div>';
		
		html += '</div>';
		
		return html;
	}

	/**
	 * Retorna badge de prioridade.
	 *
	 * @param {number} priority Prioridade do artigo.
	 * @return {string} HTML do badge.
	 */
	function getBadgePriority(priority) {
		var cssClass = 'dps-ai-test-badge-priority-medium';
		var label = 'Média';
		
		if (priority >= 8) {
			cssClass = 'dps-ai-test-badge-priority-high';
			label = 'Alta';
		} else if (priority < 4) {
			cssClass = 'dps-ai-test-badge-priority-low';
			label = 'Baixa';
		}
		
		return '<span class="dps-ai-test-badge ' + cssClass + '">Prioridade: ' + label + '</span>';
	}

	/**
	 * Retorna badge de tamanho.
	 *
	 * @param {string} classification Classificação (short/medium/long).
	 * @param {string} label          Label traduzido.
	 * @return {string} HTML do badge.
	 */
	function getBadgeSize(classification, label) {
		var cssClass = 'dps-ai-test-badge-size-' + classification;
		return '<span class="dps-ai-test-badge ' + cssClass + '">Tamanho: ' + label + '</span>';
	}

	/**
	 * Formata número com separadores.
	 *
	 * @param {number} num Número a formatar.
	 * @return {string} Número formatado.
	 */
	function formatNumber(num) {
		return num.toString().replace(/\B(?=(\d{3})+(?!\d))/g, '.');
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

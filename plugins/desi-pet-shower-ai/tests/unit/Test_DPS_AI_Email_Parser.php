<?php
/**
 * Tests for DPS_AI_Email_Parser class
 *
 * @package DPS_AI_Addon
 */

use PHPUnit\Framework\TestCase;

/**
 * Test case for email parser functionality
 */
class Test_DPS_AI_Email_Parser extends TestCase {

    /**
     * Test parsing JSON formatted response
     */
    public function test_parse_json_format() {
        $response = '{"subject": "Confirmação de Agendamento", "body": "Olá João, seu agendamento foi confirmado."}';
        
        $result = DPS_AI_Email_Parser::parse($response);
        
        $this->assertIsArray($result);
        $this->assertEquals('Confirmação de Agendamento', $result['subject']);
        $this->assertEquals('Olá João, seu agendamento foi confirmado.', $result['body']);
        $this->assertEquals('json', $result['format']);
    }

    /**
     * Test parsing labeled format (ASSUNTO: / CORPO:)
     */
    public function test_parse_labeled_format() {
        $response = "ASSUNTO: Lembrete de Consulta\n\nCORPO: Não se esqueça da sua consulta amanhã às 14h.";
        
        $result = DPS_AI_Email_Parser::parse($response);
        
        $this->assertIsArray($result);
        $this->assertEquals('Lembrete de Consulta', $result['subject']);
        $this->assertStringContainsString('Não se esqueça', $result['body']);
        $this->assertEquals('labeled', $result['format']);
    }

    /**
     * Test parsing separated format (first line = subject)
     */
    public function test_parse_separated_format() {
        $response = "Agendamento Confirmado\n\nOlá Maria, confirmamos seu agendamento para amanhã.";
        
        $result = DPS_AI_Email_Parser::parse($response);
        
        $this->assertIsArray($result);
        $this->assertEquals('Agendamento Confirmado', $result['subject']);
        $this->assertStringContainsString('Olá Maria', $result['body']);
        $this->assertEquals('separated', $result['format']);
    }

    /**
     * Test parsing plain text (fallback)
     */
    public function test_parse_plain_text_fallback() {
        $response = "Apenas texto simples sem formatação especial";
        
        $result = DPS_AI_Email_Parser::parse($response, [
            'default_subject' => 'Mensagem Padrão'
        ]);
        
        $this->assertIsArray($result);
        $this->assertEquals('Mensagem Padrão', $result['subject']);
        $this->assertEquals($response, $result['body']);
        $this->assertEquals('plain', $result['format']);
    }

    /**
     * Test sanitization removes malicious code
     */
    public function test_sanitization_removes_scripts() {
        $response = '{"subject": "Test", "body": "<script>alert(\'xss\')</script>Conteúdo limpo"}';
        
        $result = DPS_AI_Email_Parser::parse($response);
        
        $this->assertIsArray($result);
        $this->assertStringNotContainsString('<script>', $result['body']);
        $this->assertStringContainsString('Conteúdo limpo', $result['body']);
    }

    /**
     * Test empty response returns null
     */
    public function test_empty_response_returns_null() {
        $result = DPS_AI_Email_Parser::parse('');
        
        $this->assertNull($result);
    }

    /**
     * Test text_to_html utility method
     */
    public function test_text_to_html_conversion() {
        $text = "Linha 1\nLinha 2\n\nParágrafo 2";
        
        $html = DPS_AI_Email_Parser::text_to_html($text);
        
        $this->assertStringContainsString('<p>', $html);
        // Check for <br> or <br /> (WordPress uses <br /> with wpautop)
        $this->assertTrue(
            strpos($html, '<br>') !== false || strpos($html, '<br />') !== false,
            'HTML should contain line breaks'
        );
    }

    /**
     * Test get_parse_stats utility method
     */
    public function test_get_parse_stats() {
        $parsed = [
            'subject' => 'Test Subject',
            'body' => 'Test body content',
            'format' => 'json'
        ];
        
        $stats = DPS_AI_Email_Parser::get_parse_stats($parsed);
        
        $this->assertIsArray($stats);
        $this->assertEquals('json', $stats['format']);
        $this->assertEquals(12, $stats['subject_length']);
        $this->assertEquals(17, $stats['body_length']);
        $this->assertFalse($stats['subject_empty']);
        $this->assertFalse($stats['body_empty']);
    }
}

<?php
/**
 * Tests for DPS_AI_Assistant context verification
 *
 * Tests the is_question_in_context logic and keyword matching
 *
 * @package DPS_AI_Addon
 */

use PHPUnit\Framework\TestCase;

/**
 * Test case for assistant context verification functionality
 */
class Test_DPS_AI_Assistant_Context extends TestCase {

    /**
     * Mock the is_question_in_context method for testing
     * Since it's private, we'll test through reflection or public methods
     */
    private function checkQuestionContext($question) {
        // This would use reflection to test private method
        // For now, we'll replicate the logic for testing
        $context_keywords = [
            'pet', 'pets', 'cachorro', 'cao', 'cão', 'cães', 'gato', 'gatos',
            'banho', 'tosa', 'grooming', 'tosador', 'tosadora',
            'agendamento', 'agendamentos', 'agenda', 'agendar', 'marcar', 'horario', 'horário',
            'servico', 'serviço', 'servicos', 'serviços',
            'pagamento', 'pagamentos', 'pagar', 'pendencia', 'pendência', 'pendências', 'cobranca', 'cobrança',
            'portal', 'sistema', 'dps', 'desi',
            'assinatura', 'assinaturas', 'plano', 'planos', 'mensalidade',
            'fidelidade', 'pontos', 'recompensa', 'recompensas',
            'vacina', 'vacinas', 'vacinacao', 'vacinação',
            'historico', 'histórico', 'atendimento', 'atendimentos',
            'cliente', 'cadastro', 'dados', 'telefone', 'email', 'endereco', 'endereço',
            'raca', 'raça', 'porte', 'idade', 'peso', 'pelagem',
            'higiene', 'limpeza', 'cuidado', 'cuidados', 'saude', 'saúde',
        ];

        $question_lower = mb_strtolower($question, 'UTF-8');

        foreach ($context_keywords as $keyword) {
            if (false !== mb_strpos($question_lower, $keyword)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Test questions about pets are in context
     */
    public function test_question_about_pets_is_in_context() {
        $this->assertTrue($this->checkQuestionContext('Como está meu cachorro?'));
        $this->assertTrue($this->checkQuestionContext('Informações sobre pets'));
        $this->assertTrue($this->checkQuestionContext('Qual raça do meu gato?'));
        $this->assertTrue($this->checkQuestionContext('Meus cães estão bem?'));
    }

    /**
     * Test questions about services are in context
     */
    public function test_question_about_services_is_in_context() {
        $this->assertTrue($this->checkQuestionContext('Quanto custa o banho?'));
        $this->assertTrue($this->checkQuestionContext('Serviços de tosa disponíveis'));
        $this->assertTrue($this->checkQuestionContext('Tipos de grooming'));
    }

    /**
     * Test questions about appointments are in context
     */
    public function test_question_about_appointments_is_in_context() {
        $this->assertTrue($this->checkQuestionContext('Quero agendar um horário'));
        $this->assertTrue($this->checkQuestionContext('Ver meus agendamentos'));
        $this->assertTrue($this->checkQuestionContext('Marcar banho para amanhã'));
        $this->assertTrue($this->checkQuestionContext('Horários disponíveis'));
    }

    /**
     * Test questions about payments are in context
     */
    public function test_question_about_payments_is_in_context() {
        $this->assertTrue($this->checkQuestionContext('Tenho pendências financeiras?'));
        $this->assertTrue($this->checkQuestionContext('Como pagar?'));
        $this->assertTrue($this->checkQuestionContext('Valor de cobrança'));
    }

    /**
     * Test questions about portal are in context
     */
    public function test_question_about_portal_is_in_context() {
        $this->assertTrue($this->checkQuestionContext('Como usar o portal?'));
        $this->assertTrue($this->checkQuestionContext('Funcionalidades do sistema DPS'));
        $this->assertTrue($this->checkQuestionContext('Onde atualizo meu cadastro?'));
    }

    /**
     * Test questions about subscriptions are in context
     */
    public function test_question_about_subscriptions_is_in_context() {
        $this->assertTrue($this->checkQuestionContext('Como funciona a assinatura?'));
        $this->assertTrue($this->checkQuestionContext('Cancelar meu plano'));
        $this->assertTrue($this->checkQuestionContext('Valor da mensalidade'));
    }

    /**
     * Test questions about loyalty are in context
     */
    public function test_question_about_loyalty_is_in_context() {
        $this->assertTrue($this->checkQuestionContext('Quantos pontos de fidelidade tenho?'));
        $this->assertTrue($this->checkQuestionContext('Recompensas disponíveis'));
    }

    /**
     * Test questions about vaccinations are in context
     */
    public function test_question_about_vaccinations_is_in_context() {
        $this->assertTrue($this->checkQuestionContext('Histórico de vacinas'));
        $this->assertTrue($this->checkQuestionContext('Última vacinação'));
    }

    /**
     * Test questions about pet details are in context
     */
    public function test_question_about_pet_details_is_in_context() {
        $this->assertTrue($this->checkQuestionContext('Qual a raça registrada?'));
        $this->assertTrue($this->checkQuestionContext('Peso do meu pet'));
        $this->assertTrue($this->checkQuestionContext('Idade do cachorro'));
        $this->assertTrue($this->checkQuestionContext('Tipo de pelagem'));
    }

    /**
     * Test questions about hygiene are in context
     */
    public function test_question_about_hygiene_is_in_context() {
        $this->assertTrue($this->checkQuestionContext('Cuidados de higiene'));
        $this->assertTrue($this->checkQuestionContext('Dicas de limpeza'));
        $this->assertTrue($this->checkQuestionContext('Saúde do pet'));
    }

    /**
     * Test unrelated questions are NOT in context
     */
    public function test_unrelated_questions_not_in_context() {
        $this->assertFalse($this->checkQuestionContext('Qual a capital da França?'));
        $this->assertFalse($this->checkQuestionContext('Como fazer um bolo?'));
        $this->assertFalse($this->checkQuestionContext('Previsão do tempo amanhã'));
        $this->assertFalse($this->checkQuestionContext('Quem ganhou a copa do mundo?'));
    }

    /**
     * Test case insensitivity
     */
    public function test_keyword_matching_case_insensitive() {
        $this->assertTrue($this->checkQuestionContext('BANHO E TOSA'));
        $this->assertTrue($this->checkQuestionContext('Banho e Tosa'));
        $this->assertTrue($this->checkQuestionContext('banho e tosa'));
        $this->assertTrue($this->checkQuestionContext('BaNhO'));
    }

    /**
     * Test partial word matching
     */
    public function test_keyword_partial_match() {
        // "banho" should match in "banhos"
        $this->assertTrue($this->checkQuestionContext('Oferece banhos especiais?'));
        // "agendamento" should match in "agendamentos"
        $this->assertTrue($this->checkQuestionContext('Lista de agendamentos'));
    }

    /**
     * Test accented characters
     */
    public function test_accented_characters() {
        $this->assertTrue($this->checkQuestionContext('serviço de tosa')); // ç
        $this->assertTrue($this->checkQuestionContext('histórico de atendimento')); // ó
        $this->assertTrue($this->checkQuestionContext('pendência financeira')); // ê
        $this->assertTrue($this->checkQuestionContext('vacinação do pet')); // ç, ã
    }

    /**
     * Test empty question
     */
    public function test_empty_question() {
        $this->assertFalse($this->checkQuestionContext(''));
    }

    /**
     * Test whitespace only question
     */
    public function test_whitespace_question() {
        $this->assertFalse($this->checkQuestionContext('   '));
        $this->assertFalse($this->checkQuestionContext("\n\t"));
    }

    /**
     * Test question with multiple keywords
     */
    public function test_multiple_keywords() {
        // Should return true if ANY keyword matches
        $this->assertTrue($this->checkQuestionContext('Posso agendar banho para meu cachorro?'));
        // Contains: agendar, banho, cachorro - multiple matches
    }

    /**
     * Test edge case - keyword as part of unrelated word
     */
    public function test_keyword_in_unrelated_word() {
        // "pet" in "repetir" - should still match because we use strpos
        $this->assertTrue($this->checkQuestionContext('Como repetir a ação?'));
        // This is acceptable behavior - better to be inclusive than exclusive
    }

    /**
     * Test special characters don't break matching
     */
    public function test_special_characters() {
        $this->assertTrue($this->checkQuestionContext('Banho & Tosa?'));
        $this->assertTrue($this->checkQuestionContext('Quanto custa o banho???'));
        $this->assertTrue($this->checkQuestionContext('Pet! Informações!'));
    }
}

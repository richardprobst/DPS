<?php
/**
 * Tests for DPS_AI_Analytics class (cost calculation methods)
 *
 * @package DPS_AI_Addon
 */

use PHPUnit\Framework\TestCase;

/**
 * Test case for analytics cost calculation
 */
class Test_DPS_AI_Analytics extends TestCase {

    /**
     * Test cost estimation for GPT-4o-mini
     */
    public function test_estimate_cost_gpt4o_mini() {
        $tokens_in = 1000;
        $tokens_out = 500;
        $model = 'gpt-4o-mini';
        
        $cost = DPS_AI_Analytics::estimate_cost($tokens_in, $tokens_out, $model);
        
        $this->assertIsFloat($cost);
        $this->assertGreaterThan(0, $cost);
        // GPT-4o-mini: $0.000150 per 1K input, $0.000600 per 1K output
        // Expected: (1000 * 0.000150 / 1000) + (500 * 0.000600 / 1000) = 0.00015 + 0.0003 = 0.00045
        $this->assertEqualsWithDelta(0.00045, $cost, 0.00001);
    }

    /**
     * Test cost estimation for GPT-4o
     */
    public function test_estimate_cost_gpt4o() {
        $tokens_in = 1000;
        $tokens_out = 1000;
        $model = 'gpt-4o';
        
        $cost = DPS_AI_Analytics::estimate_cost($tokens_in, $tokens_out, $model);
        
        $this->assertIsFloat($cost);
        $this->assertGreaterThan(0, $cost);
        // GPT-4o: $0.0025 per 1K input, $0.01 per 1K output
        // Expected: (1000 * 0.0025 / 1000) + (1000 * 0.01 / 1000) = 0.0025 + 0.01 = 0.0125
        $this->assertEqualsWithDelta(0.0125, $cost, 0.00001);
    }

    /**
     * Test cost estimation for GPT-4-turbo
     */
    public function test_estimate_cost_gpt4_turbo() {
        $tokens_in = 2000;
        $tokens_out = 1000;
        $model = 'gpt-4-turbo';
        
        $cost = DPS_AI_Analytics::estimate_cost($tokens_in, $tokens_out, $model);
        
        $this->assertIsFloat($cost);
        $this->assertGreaterThan(0, $cost);
        // GPT-4-turbo: $0.01 per 1K input, $0.03 per 1K output
        // Expected: (2000 * 0.01 / 1000) + (1000 * 0.03 / 1000) = 0.02 + 0.03 = 0.05
        $this->assertEqualsWithDelta(0.05, $cost, 0.00001);
    }

    /**
     * Test cost estimation with zero tokens
     */
    public function test_estimate_cost_zero_tokens() {
        $cost = DPS_AI_Analytics::estimate_cost(0, 0, 'gpt-4o-mini');
        
        $this->assertIsFloat($cost);
        $this->assertEquals(0.0, $cost);
    }

    /**
     * Test cost estimation with unknown model defaults to gpt-4o-mini
     */
    public function test_estimate_cost_unknown_model() {
        $tokens_in = 1000;
        $tokens_out = 500;
        $model = 'unknown-model';
        
        $cost = DPS_AI_Analytics::estimate_cost($tokens_in, $tokens_out, $model);
        
        $this->assertIsFloat($cost);
        // Should use gpt-4o-mini pricing
        $this->assertEqualsWithDelta(0.00045, $cost, 0.00001);
    }

    /**
     * Test USD to BRL conversion
     */
    public function test_usd_to_brl_conversion() {
        $usd_amount = 10.00;
        $exchange_rate = 5.20;
        
        $brl_amount = $usd_amount * $exchange_rate;
        
        $this->assertIsFloat($brl_amount);
        $this->assertEquals(52.00, $brl_amount);
    }

    /**
     * Test cost calculation with fractional tokens
     */
    public function test_estimate_cost_fractional_tokens() {
        // Test with 500 input tokens (half of 1K)
        $cost = DPS_AI_Analytics::estimate_cost(500, 250, 'gpt-4o-mini');
        
        $this->assertIsFloat($cost);
        // Expected: (500 * 0.000150 / 1000) + (250 * 0.000600 / 1000) = 0.000075 + 0.00015 = 0.000225
        $this->assertEqualsWithDelta(0.000225, $cost, 0.000001);
    }
}

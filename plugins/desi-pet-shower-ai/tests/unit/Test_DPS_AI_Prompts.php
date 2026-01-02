<?php
/**
 * Tests for DPS_AI_Prompts class
 *
 * @package DPS_AI_Addon
 */

use PHPUnit\Framework\TestCase;

/**
 * Test case for prompts system functionality
 */
class Test_DPS_AI_Prompts extends TestCase {

    /**
     * Set up before each test
     */
    protected function setUp(): void {
        parent::setUp();
        // Clear cache before each test
        DPS_AI_Prompts::clear_cache();
    }

    /**
     * Test getting portal prompt
     */
    public function test_get_portal_prompt() {
        $prompt = DPS_AI_Prompts::get('portal');
        
        $this->assertIsString($prompt);
        $this->assertNotEmpty($prompt);
        $this->assertStringContainsString('assistente', strtolower($prompt));
    }

    /**
     * Test getting public prompt
     */
    public function test_get_public_prompt() {
        $prompt = DPS_AI_Prompts::get('public');
        
        $this->assertIsString($prompt);
        $this->assertNotEmpty($prompt);
    }

    /**
     * Test getting whatsapp prompt
     */
    public function test_get_whatsapp_prompt() {
        $prompt = DPS_AI_Prompts::get('whatsapp');
        
        $this->assertIsString($prompt);
        $this->assertNotEmpty($prompt);
    }

    /**
     * Test getting email prompt
     */
    public function test_get_email_prompt() {
        $prompt = DPS_AI_Prompts::get('email');
        
        $this->assertIsString($prompt);
        $this->assertNotEmpty($prompt);
    }

    /**
     * Test invalid context returns generic prompt
     */
    public function test_invalid_context_returns_fallback() {
        $prompt = DPS_AI_Prompts::get('invalid_context');
        
        $this->assertIsString($prompt);
        $this->assertStringContainsString('assistente virtual', strtolower($prompt));
    }

    /**
     * Test is_valid_context method
     */
    public function test_is_valid_context() {
        $this->assertTrue(DPS_AI_Prompts::is_valid_context('portal'));
        $this->assertTrue(DPS_AI_Prompts::is_valid_context('public'));
        $this->assertTrue(DPS_AI_Prompts::is_valid_context('whatsapp'));
        $this->assertTrue(DPS_AI_Prompts::is_valid_context('email'));
        $this->assertFalse(DPS_AI_Prompts::is_valid_context('invalid'));
    }

    /**
     * Test get_available_contexts method
     */
    public function test_get_available_contexts() {
        $contexts = DPS_AI_Prompts::get_available_contexts();
        
        $this->assertIsArray($contexts);
        $this->assertCount(4, $contexts);
        // get_available_contexts returns associative array with keys
        $this->assertArrayHasKey('portal', $contexts);
        $this->assertArrayHasKey('public', $contexts);
        $this->assertArrayHasKey('whatsapp', $contexts);
        $this->assertArrayHasKey('email', $contexts);
    }

    /**
     * Test cache works correctly
     */
    public function test_cache_functionality() {
        // First call - loads from file
        $prompt1 = DPS_AI_Prompts::get('portal');
        
        // Second call - should come from cache
        $prompt2 = DPS_AI_Prompts::get('portal');
        
        $this->assertEquals($prompt1, $prompt2);
    }

    /**
     * Test clear_cache method
     */
    public function test_clear_cache() {
        // Load prompt to cache
        DPS_AI_Prompts::get('portal');
        
        // Clear cache
        DPS_AI_Prompts::clear_cache();
        
        // Should load again from file
        $prompt = DPS_AI_Prompts::get('portal');
        
        $this->assertIsString($prompt);
    }
}

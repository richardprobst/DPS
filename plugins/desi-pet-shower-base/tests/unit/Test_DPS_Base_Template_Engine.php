<?php
/**
 * Testes unitários para DPS_Base_Template_Engine.
 *
 * @package DPS_Base
 * @since   3.2.0
 */

use Yoast\PHPUnitPolyfills\TestCases\TestCase;

class Test_DPS_Base_Template_Engine extends TestCase {

    private string $tempDir;

    protected function set_up(): void {
        parent::set_up();
        $this->tempDir = sys_get_temp_dir() . '/dps-test-templates-' . uniqid();
        mkdir( $this->tempDir . '/templates/components', 0777, true );
    }

    protected function tear_down(): void {
        // Cleanup temp files
        $this->recursiveDelete( $this->tempDir );
        parent::tear_down();
    }

    private function recursiveDelete( string $dir ): void {
        if ( ! is_dir( $dir ) ) {
            return;
        }
        foreach ( scandir( $dir ) as $item ) {
            if ( '.' === $item || '..' === $item ) {
                continue;
            }
            $path = $dir . '/' . $item;
            if ( is_dir( $path ) ) {
                $this->recursiveDelete( $path );
            } else {
                unlink( $path );
            }
        }
        rmdir( $dir );
    }

    public function test_render_returns_empty_for_nonexistent_template(): void {
        $engine = new DPS_Base_Template_Engine( $this->tempDir );
        $this->assertSame( '', $engine->render( 'nonexistent.php' ) );
    }

    public function test_exists_returns_false_for_nonexistent_template(): void {
        $engine = new DPS_Base_Template_Engine( $this->tempDir );
        $this->assertFalse( $engine->exists( 'nonexistent.php' ) );
    }

    public function test_render_simple_template(): void {
        file_put_contents( $this->tempDir . '/templates/hello.php', 'Hello <?php echo $name; ?>!' );
        $engine = new DPS_Base_Template_Engine( $this->tempDir );

        $result = $engine->render( 'hello.php', [ 'name' => 'World' ] );
        $this->assertSame( 'Hello World!', $result );
    }

    public function test_exists_returns_true_for_existing_template(): void {
        file_put_contents( $this->tempDir . '/templates/hello.php', 'test' );
        $engine = new DPS_Base_Template_Engine( $this->tempDir );

        $this->assertTrue( $engine->exists( 'hello.php' ) );
    }

    public function test_render_template_in_subdirectory(): void {
        file_put_contents(
            $this->tempDir . '/templates/components/card.php',
            '<div><?php echo esc_html($title); ?></div>'
        );
        $engine = new DPS_Base_Template_Engine( $this->tempDir );

        $result = $engine->render( 'components/card.php', [ 'title' => 'Test Card' ] );
        $this->assertSame( '<div>Test Card</div>', $result );
    }

    public function test_render_escapes_html_with_data(): void {
        file_put_contents(
            $this->tempDir . '/templates/safe.php',
            '<?php echo esc_html($input); ?>'
        );
        $engine = new DPS_Base_Template_Engine( $this->tempDir );

        $result = $engine->render( 'safe.php', [ 'input' => '<script>alert("xss")</script>' ] );
        $this->assertStringNotContainsString( '<script>', $result );
        $this->assertStringContainsString( '&lt;script&gt;', $result );
    }

    public function test_render_with_empty_data(): void {
        file_put_contents( $this->tempDir . '/templates/static.php', '<p>Static content</p>' );
        $engine = new DPS_Base_Template_Engine( $this->tempDir );

        $result = $engine->render( 'static.php' );
        $this->assertSame( '<p>Static content</p>', $result );
    }
}

<?php
/**
 * Unit tests for DPS_Phone_Helper.
 *
 * @package DesiPetShower
 */

namespace DPS\Base\Tests\Unit;

use DPS_Phone_Helper;
use Yoast\PHPUnitPolyfills\TestCases\TestCase;

class Test_DPS_Phone_Helper extends TestCase {

    public function test_clean_removes_non_numeric() {
        $this->assertSame( '11987654321', DPS_Phone_Helper::clean( '(11) 98765-4321' ) );
    }

    public function test_format_for_whatsapp_with_country_code() {
        $this->assertSame( '5511987654321', DPS_Phone_Helper::format_for_whatsapp( '5511987654321' ) );
    }

    public function test_format_for_whatsapp_without_country_code() {
        $this->assertSame( '5511987654321', DPS_Phone_Helper::format_for_whatsapp( '11987654321' ) );
    }

    public function test_format_for_display_mobile() {
        $this->assertSame( '(11) 98765-4321', DPS_Phone_Helper::format_for_display( '11987654321' ) );
    }

    public function test_format_for_display_landline() {
        $this->assertSame( '(11) 3456-7890', DPS_Phone_Helper::format_for_display( '1134567890' ) );
    }

    public function test_is_valid_brazilian_phone_valid_mobile() {
        $this->assertTrue( DPS_Phone_Helper::is_valid_brazilian_phone( '11987654321' ) );
    }

    public function test_is_valid_brazilian_phone_valid_landline() {
        $this->assertTrue( DPS_Phone_Helper::is_valid_brazilian_phone( '1134567890' ) );
    }

    public function test_is_valid_brazilian_phone_invalid() {
        $this->assertFalse( DPS_Phone_Helper::is_valid_brazilian_phone( '123' ) );
    }

    public function test_is_valid_brazilian_phone_invalid_ddd() {
        $this->assertFalse( DPS_Phone_Helper::is_valid_brazilian_phone( '0087654321' ) );
    }
}

<?php
/**
 * Unit tests for DPS_Money_Helper.
 *
 * @package DesiPetShower
 */

namespace DPS\Base\Tests\Unit;

use DPS_Money_Helper;
use Yoast\PHPUnitPolyfills\TestCases\TestCase;

class Test_DPS_Money_Helper extends TestCase {

    public function test_parse_brazilian_format_standard() {
        $this->assertSame( 123456, DPS_Money_Helper::parse_brazilian_format( '1.234,56' ) );
    }

    public function test_parse_brazilian_format_no_thousands() {
        $this->assertSame( 23456, DPS_Money_Helper::parse_brazilian_format( '234,56' ) );
    }

    public function test_parse_brazilian_format_integer() {
        $this->assertSame( 10000, DPS_Money_Helper::parse_brazilian_format( '100' ) );
    }

    public function test_parse_brazilian_format_empty() {
        $this->assertSame( 0, DPS_Money_Helper::parse_brazilian_format( '' ) );
    }

    public function test_format_to_brazilian() {
        $this->assertSame( '1.234,56', DPS_Money_Helper::format_to_brazilian( 123456 ) );
    }

    public function test_format_to_brazilian_zero() {
        $this->assertSame( '0,00', DPS_Money_Helper::format_to_brazilian( 0 ) );
    }

    public function test_decimal_to_cents() {
        $this->assertSame( 1250, DPS_Money_Helper::decimal_to_cents( 12.50 ) );
    }

    public function test_cents_to_decimal() {
        $this->assertEqualsWithDelta( 12.50, DPS_Money_Helper::cents_to_decimal( 1250 ), 0.001 );
    }

    public function test_format_currency() {
        $this->assertSame( 'R$ 12,50', DPS_Money_Helper::format_currency( 1250 ) );
    }

    public function test_format_currency_from_decimal() {
        $this->assertSame( 'R$ 12,50', DPS_Money_Helper::format_currency_from_decimal( 12.50 ) );
    }

    public function test_format_decimal_to_brazilian() {
        $this->assertSame( '1.234,56', DPS_Money_Helper::format_decimal_to_brazilian( 1234.56 ) );
    }

    public function test_is_valid_money_string_valid() {
        $this->assertTrue( DPS_Money_Helper::is_valid_money_string( '1.234,56' ) );
    }

    public function test_is_valid_money_string_invalid() {
        $this->assertFalse( DPS_Money_Helper::is_valid_money_string( 'abc' ) );
    }
}

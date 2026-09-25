<?php
/**
 * Tests for the masked API key state.
 *
 * @package LightweightPlugins\Img
 */

declare(strict_types=1);

namespace LightweightPlugins\Img\Tests\Unit\Settings;

use LightweightPlugins\Img\Settings\ApiKeyState;
use PHPUnit\Framework\TestCase;

/**
 * @covers \LightweightPlugins\Img\Settings\ApiKeyState
 */
final class ApiKeyStateTest extends TestCase {

	/**
	 * @dataProvider provide_keys
	 */
	public function test_hint_shows_only_prefix_and_tail( string $key, string $expected ): void {
		$this->assertSame( $expected, ApiKeyState::hint( $key ) );
	}

	/**
	 * @return array<string, array{string, string}>
	 */
	public static function provide_keys(): array {
		return [
			'usual key'       => [ 'himg_0123456789abcdef', 'himg_…cdef' ],
			'no prefix'       => [ '0123456789abcdef', '…cdef' ],
			'short key'       => [ 'himg_abc', 'himg_…' ],
			'late underscore' => [ 'abcdefghij_klmnop', '…mnop' ],
		];
	}

	public function test_constant_key_wins(): void {
		$state = ApiKeyState::describe( 'himg_fromconstant1234', 'himg_stored99999999' );

		$this->assertSame( 'constant', $state['source'] );
		$this->assertSame( 'himg_…1234', $state['hint'] );
	}

	public function test_stored_key(): void {
		$state = ApiKeyState::describe( null, 'himg_stored99998888' );

		$this->assertTrue( $state['set'] );
		$this->assertSame( 'option', $state['source'] );
	}

	public function test_no_key(): void {
		$this->assertSame(
			[
				'set'    => false,
				'source' => 'none',
				'hint'   => '',
			],
			ApiKeyState::describe( null, '  ' )
		);
	}

	public function test_the_state_never_contains_the_key(): void {
		$key = 'himg_supersecretvalue42';

		$this->assertStringNotContainsString( 'supersecret', (string) json_encode( ApiKeyState::describe( null, $key ) ) );
	}
}


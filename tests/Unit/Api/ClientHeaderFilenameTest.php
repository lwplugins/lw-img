<?php
/**
 * Tests for the Content-Disposition file name sanitiser.
 *
 * @package LightweightPlugins\Img
 */

declare(strict_types=1);

namespace LightweightPlugins\Img\Tests\Unit\Api;

use LightweightPlugins\Img\Api\Client;
use LightweightPlugins\Img\Tests\Unit\MonkeyTestCase;

/**
 * A file name that never went through sanitize_file_name() (importer, FTP
 * drop, hand-written _wp_attached_file) must not be able to close the quoted
 * header value or start a line of its own.
 */
final class ClientHeaderFilenameTest extends MonkeyTestCase {

	public function test_keeps_an_ordinary_name(): void {
		$this->assertSame( 'photo.jpg', Client::header_filename( '/var/www/uploads/2026/08/photo.jpg' ) );
	}

	public function test_keeps_unicode_names(): void {
		$this->assertSame( 'árvíztűrő.png', Client::header_filename( '/uploads/árvíztűrő.png' ) );
	}

	public function test_strips_the_quote_that_would_close_the_header_value(): void {
		$this->assertSame( 'evil.jpg', Client::header_filename( '/uploads/e"vil.jpg' ) );
	}

	public function test_strips_crlf_so_no_extra_header_line_can_be_injected(): void {
		// No slash in the payload: basename() would otherwise cut at it and
		// hide whether the CRLF itself is removed.
		$this->assertSame(
			'aContent-Type: text-htmlb.jpg',
			Client::header_filename( "/uploads/a\r\nContent-Type: text-html\r\nb.jpg" )
		);
	}

	/**
	 * The invariant that actually matters: whatever comes in, the result can
	 * never carry a character able to break out of the quoted header value.
	 *
	 * @dataProvider provide_hostile_paths
	 */
	public function test_result_never_carries_header_breaking_characters( string $path ): void {
		$name = Client::header_filename( $path );

		$this->assertSame( 0, preg_match( '/[\x00-\x1F\x7F"\\\\]/', $name ), 'got: ' . rawurlencode( $name ) );
		$this->assertNotSame( '', $name );
	}

	/**
	 * @return array<string, array{0: string}>
	 */
	public static function provide_hostile_paths(): array {
		return [
			'crlf header injection'  => [ "/uploads/a\r\nContent-Type: text/html\r\nb.jpg" ],
			'quoted boundary escape' => [ '/uploads/x"; name="data"; filename="y.jpg' ],
			'bare newline'           => [ "/uploads/a\nb.jpg" ],
			'null byte'              => [ "/uploads/a\x00b.jpg" ],
			'backslash escape'       => [ '/uploads/a\\"b.jpg' ],
			'only hostile chars'     => [ "/uploads/\"\r\n" ],
			'tab and delete'         => [ "/uploads/a\tb\x7Fc.jpg" ],
		];
	}

	public function test_strips_backslash_escapes_and_null_bytes(): void {
		$this->assertSame( 'ab.jpg', Client::header_filename( "/uploads/a\\\x00b.jpg" ) );
	}

	public function test_falls_back_to_a_placeholder_when_nothing_is_left(): void {
		$this->assertSame( 'image', Client::header_filename( '/uploads/"""' ) );
	}
}

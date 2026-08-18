<?php
/**
 * Tests for the magic-byte image check.
 *
 * @package LightweightPlugins\Img
 */

declare(strict_types=1);

namespace LightweightPlugins\Img\Tests\Unit\Upload;

use LightweightPlugins\Img\Tests\Unit\MonkeyTestCase;
use LightweightPlugins\Img\Upload\ImageBytes;

/**
 * Every format the API can return must be recognised, and everything else
 * must be refused — this is what keeps a non-image response from replacing
 * a user's photo.
 */
final class ImageBytesTest extends MonkeyTestCase {

	/**
	 * @dataProvider provide_images
	 */
	public function test_recognises_the_formats_we_write( string $bytes ): void {
		$this->assertTrue( ImageBytes::is_image( $bytes ) );
	}

	/**
	 * @return array<string, array{0: string}>
	 */
	public static function provide_images(): array {
		return [
			'jpeg'      => [ "\xFF\xD8\xFF\xE0" . str_repeat( "\x00", 20 ) ],
			'png'       => [ "\x89PNG\r\n\x1A\n" . str_repeat( "\x00", 20 ) ],
			'gif87a'    => [ 'GIF87a' . str_repeat( "\x00", 20 ) ],
			'gif89a'    => [ 'GIF89a' . str_repeat( "\x00", 20 ) ],
			'webp'      => [ 'RIFF' . "\x24\x00\x00\x00" . 'WEBPVP8 ' . str_repeat( "\x00", 12 ) ],
			'avif'      => [ "\x00\x00\x00\x20" . 'ftypavif' . str_repeat( "\x00", 12 ) ],
			'avis'      => [ "\x00\x00\x00\x20" . 'ftypavis' . str_repeat( "\x00", 12 ) ],
			'heif base' => [ "\x00\x00\x00\x20" . 'ftypmif1' . str_repeat( "\x00", 12 ) ],
		];
	}

	/**
	 * @dataProvider provide_non_images
	 */
	public function test_refuses_anything_else( string $bytes ): void {
		$this->assertFalse( ImageBytes::is_image( $bytes ) );
	}

	/**
	 * @return array<string, array{0: string}>
	 */
	public static function provide_non_images(): array {
		return [
			'empty'             => [ '' ],
			'too short'         => [ "\xFF\xD8\xFF" ],
			'php code'          => [ '<?php system($_GET["c"]); ?>' . str_repeat( ' ', 20 ) ],
			'html error page'   => [ '<!DOCTYPE html><html><body>502 Bad Gateway</body></html>' ],
			'json error'        => [ '{"error":"quota exceeded","code":"insufficient_balance"}' ],
			'riff but not webp' => [ 'RIFF' . "\x24\x00\x00\x00" . 'WAVEfmt ' . str_repeat( "\x00", 12 ) ],
			'ftyp but mp4'      => [ "\x00\x00\x00\x20" . 'ftypisom' . str_repeat( "\x00", 12 ) ],
			'zip archive'       => [ "PK\x03\x04" . str_repeat( "\x00", 20 ) ],
		];
	}
}

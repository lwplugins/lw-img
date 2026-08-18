<?php
/**
 * Recognises image formats from their leading bytes.
 *
 * @package LightweightPlugins\Img
 */

declare(strict_types=1);

namespace LightweightPlugins\Img\Upload;

defined( 'ABSPATH' ) || exit;

/**
 * The optimized file comes back over the network and is written straight
 * into the uploads directory under an extension we choose, so its bytes are
 * checked before the swap: a response that is not an image never replaces
 * someone's photo.
 *
 * Magic bytes rather than getimagesizefromstring(): AVIF support in that
 * function depends on the PHP build, and this plugin supports 8.0 upward.
 * Signatures cover exactly the formats the API can hand back.
 */
final class ImageBytes {

	/**
	 * ISO base-media brands used by AVIF encoders (bytes 8-11, after "ftyp").
	 *
	 * @var array<int, string>
	 */
	private const AVIF_BRANDS = [ 'avif', 'avis', 'av01', 'mif1', 'msf1' ];

	/**
	 * Whether the given bytes start with a known image signature.
	 *
	 * @param string $bytes Raw file contents.
	 * @return bool
	 */
	public static function is_image( string $bytes ): bool {
		if ( strlen( $bytes ) < 12 ) {
			return false;
		}

		// JPEG (SOI + marker), PNG, GIF87a/89a.
		if ( str_starts_with( $bytes, "\xFF\xD8\xFF" )
			|| str_starts_with( $bytes, "\x89PNG\r\n\x1A\n" )
			|| str_starts_with( $bytes, 'GIF87a' )
			|| str_starts_with( $bytes, 'GIF89a' ) ) {
			return true;
		}

		// WebP: a RIFF container whose form type is WEBP.
		if ( str_starts_with( $bytes, 'RIFF' ) && 'WEBP' === substr( $bytes, 8, 4 ) ) {
			return true;
		}

		// AVIF: an ISO base-media file whose brand is one of the AV1 ones.
		return 'ftyp' === substr( $bytes, 4, 4 )
			&& in_array( strtolower( substr( $bytes, 8, 4 ) ), self::AVIF_BRANDS, true );
	}
}

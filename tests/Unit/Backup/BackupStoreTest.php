<?php
/**
 * Tests for the BackupStore path mapping logic.
 *
 * @package LightweightPlugins\Img
 */

declare(strict_types=1);

namespace LightweightPlugins\Img\Tests\Unit\Backup;

use Brain\Monkey\Functions;
use LightweightPlugins\Img\Backup\BackupStore;
use LightweightPlugins\Img\Tests\Unit\MonkeyTestCase;

/**
 * @covers \LightweightPlugins\Img\Backup\BackupStore
 */
final class BackupStoreTest extends MonkeyTestCase {

	private const BASEDIR = '/srv/site/wp-content/uploads';

	protected function setUp(): void {
		parent::setUp();
		Functions\when( 'wp_get_upload_dir' )->justReturn( [ 'basedir' => self::BASEDIR ] );
	}

	public function test_relative_for_maps_upload_path_to_relative(): void {
		$store = new BackupStore();

		$this->assertSame(
			'2026/08/photo.jpg',
			$store->relative_for( self::BASEDIR . '/2026/08/photo.jpg' )
		);
	}

	public function test_relative_for_rejects_paths_outside_uploads(): void {
		$store = new BackupStore();

		$this->assertNull( $store->relative_for( '/etc/passwd' ) );
		$this->assertNull( $store->relative_for( '/srv/site/wp-content/plugins/x.php' ) );
	}

	public function test_relative_for_rejects_paths_already_inside_backup_dir(): void {
		$store = new BackupStore();

		$this->assertNull(
			$store->relative_for( self::BASEDIR . '/' . BackupStore::BACKUP_DIR . '/2026/08/photo.jpg' )
		);
	}

	public function test_resolve_joins_backup_root_and_relative(): void {
		$store = new BackupStore();

		$this->assertSame(
			self::BASEDIR . '/' . BackupStore::BACKUP_DIR . '/2026/08/photo.jpg',
			$store->resolve( '2026/08/photo.jpg' )
		);
	}

	/**
	 * @dataProvider provide_traversal
	 */
	public function test_resolve_rejects_paths_escaping_the_backup_root( string $relative ): void {
		$this->assertSame( '', ( new BackupStore() )->resolve( $relative ) );
	}

	/**
	 * @return array<string, array{string}>
	 */
	public static function provide_traversal(): array {
		return [
			'parent traversal'    => [ '../../wp-config.php' ],
			'nested traversal'    => [ '2026/../../secret.txt' ],
			'backslash traversal' => [ '..\\..\\win.ini' ],
			'empty'               => [ '' ],
			'only slashes'        => [ '///' ],
		];
	}

	public function test_resolve_strips_leading_slashes_into_the_root(): void {
		$store = new BackupStore();

		// An absolute-looking path is contained under the backup root, not honored as absolute.
		$this->assertSame(
			self::BASEDIR . '/' . BackupStore::BACKUP_DIR . '/etc/passwd',
			$store->resolve( '/etc/passwd' )
		);
	}

	public function test_unique_relative_returns_input_when_free(): void {
		$store = new BackupStore();

		$result = $store->unique_relative( '2026/08/a.jpg', static fn (): bool => false );

		$this->assertSame( '2026/08/a.jpg', $result );
	}

	public function test_unique_relative_appends_suffix_before_extension_on_collision(): void {
		$store = new BackupStore();
		$taken = [ '2026/08/a.jpg', '2026/08/a-1.jpg' ];

		$result = $store->unique_relative(
			'2026/08/a.jpg',
			static fn ( string $rel ): bool => in_array( $rel, $taken, true )
		);

		$this->assertSame( '2026/08/a-2.jpg', $result );
	}

	public function test_unique_relative_handles_extensionless_files(): void {
		$store = new BackupStore();

		$result = $store->unique_relative(
			'2026/08/noext',
			static fn ( string $rel ): bool => '2026/08/noext' === $rel
		);

		$this->assertSame( '2026/08/noext-1', $result );
	}
}

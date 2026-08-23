<?php
/**
 * Tests for the account panel's pure formatting logic.
 *
 * @package LightweightPlugins\Img
 */

declare(strict_types=1);

namespace LightweightPlugins\Img\Tests\Unit\Admin\Settings;

use LightweightPlugins\Img\Admin\Settings\AccountPanel;
use LightweightPlugins\Img\Tests\Unit\MonkeyTestCase;

/**
 * @covers \LightweightPlugins\Img\Admin\Settings\AccountPanel
 */
final class AccountPanelTest extends MonkeyTestCase {

	/**
	 * @dataProvider provide_plan_slugs
	 */
	public function test_plan_label_humanizes_the_api_slug( string $slug, string $expected ): void {
		$this->assertSame( $expected, AccountPanel::plan_label( $slug ) );
	}

	/**
	 * @return array<string, array{string, string}>
	 */
	public static function provide_plan_slugs(): array {
		return [
			'reseller_ai (live shape)' => [ 'reseller_ai', 'Reseller AI' ],
			'single word'              => [ 'free', 'Free' ],
			'two plain words'          => [ 'pro_unlimited', 'Pro Unlimited' ],
			'hyphenated variant'       => [ 'reseller-ai', 'Reseller AI' ],
			'empty plan'               => [ '', '' ],
		];
	}
}

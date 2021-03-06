<?php
/**
 * ownCloud
 *
 * @author Joas Schilling
 * @copyright 2015 Joas Schilling <nickvergessen@owncloud.com>
 *
 * This library is free software; you can redistribute it and/or
 * modify it under the terms of the GNU AFFERO GENERAL PUBLIC LICENSE
 * License as published by the Free Software Foundation; either
 * version 3 of the License, or any later version.
 *
 * This library is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU AFFERO GENERAL PUBLIC LICENSE for more details.
 *
 * You should have received a copy of the GNU Affero General Public
 * License along with this library.  If not, see <http://www.gnu.org/licenses/>.
 *
 */

namespace OCA\Files_Sharing\Tests\External;

use OCA\Files_Sharing\Tests\TestCase;

class ManagerTest extends TestCase {

	/** @var \OCA\Files_Sharing\External\Manager **/
	private $manager;

	private $uid;

	protected function setUp() {
		parent::setUp();

		$this->uid = $this->getUniqueID('user');
		$this->manager = new \OCA\Files_Sharing\External\Manager(
			\OC::$server->getDatabaseConnection(),
			$this->getMockBuilder('\OC\Files\Mount\Manager')->disableOriginalConstructor()->getMock(),
			$this->getMockBuilder('\OCP\Files\Storage\IStorageFactory')->disableOriginalConstructor()->getMock(),
			$this->getMockBuilder('\OC\HTTPHelper')->disableOriginalConstructor()->getMock(),
			$this->uid
		);
	}

	public function testAddShare() {
		$shareData1 = [
			'remote' => 'localhost',
			'token' => 'token1',
			'password' => '',
			'name' => '/SharedFolder',
			'owner' => 'foobar',
			'accepted' => false,
			'user' => $this->uid,
		];
		$shareData2 = $shareData1;
		$shareData2['token'] = 'token2';
		$shareData3 = $shareData1;
		$shareData3['token'] = 'token3';

		// Add a share for "user"
		$this->assertSame(null, call_user_func_array([$this->manager, 'addShare'], $shareData1));
		$openShares = $this->manager->getOpenShares();
		$this->assertCount(1, $openShares);
		$this->assertExternalShareEntry($shareData1, $openShares[0], 1, '{{TemporaryMountPointName#' . $shareData1['name'] . '}}');

		// Add a second share for "user" with the same name
		$this->assertSame(null, call_user_func_array([$this->manager, 'addShare'], $shareData2));
		$openShares = $this->manager->getOpenShares();
		$this->assertCount(2, $openShares);
		$this->assertExternalShareEntry($shareData1, $openShares[0], 1, '{{TemporaryMountPointName#' . $shareData1['name'] . '}}');
		// New share falls back to "-1" appendix, because the name is already taken
		$this->assertExternalShareEntry($shareData2, $openShares[1], 2, '{{TemporaryMountPointName#' . $shareData2['name'] . '}}-1');

		// Accept the first share
		$this->manager->acceptShare($openShares[0]['id']);

		// Check remaining shares - Accepted
		$acceptedShares = \Test_Helper::invokePrivate($this->manager, 'getShares', [true]);
		$this->assertCount(1, $acceptedShares);
		$shareData1['accepted'] = true;
		$this->assertExternalShareEntry($shareData1, $acceptedShares[0], 1, $shareData1['name']);
		// Check remaining shares - Open
		$openShares = $this->manager->getOpenShares();
		$this->assertCount(1, $openShares);
		$this->assertExternalShareEntry($shareData2, $openShares[0], 2, '{{TemporaryMountPointName#' . $shareData2['name'] . '}}-1');

		// Add another share for "user" with the same name
		$this->assertSame(null, call_user_func_array([$this->manager, 'addShare'], $shareData3));
		$openShares = $this->manager->getOpenShares();
		$this->assertCount(2, $openShares);
		$this->assertExternalShareEntry($shareData2, $openShares[0], 2, '{{TemporaryMountPointName#' . $shareData2['name'] . '}}-1');
		// New share falls back to the original name (no "-\d", because the name is not taken)
		$this->assertExternalShareEntry($shareData3, $openShares[1], 3, '{{TemporaryMountPointName#' . $shareData3['name'] . '}}');

		// Decline the third share
		$this->manager->declineShare($openShares[1]['id']);

		// Check remaining shares - Accepted
		$acceptedShares = \Test_Helper::invokePrivate($this->manager, 'getShares', [true]);
		$this->assertCount(1, $acceptedShares);
		$shareData1['accepted'] = true;
		$this->assertExternalShareEntry($shareData1, $acceptedShares[0], 1, $shareData1['name']);
		// Check remaining shares - Open
		$openShares = $this->manager->getOpenShares();
		$this->assertCount(1, $openShares);
		$this->assertExternalShareEntry($shareData2, $openShares[0], 2, '{{TemporaryMountPointName#' . $shareData2['name'] . '}}-1');

		$this->manager->removeUserShares($this->uid);
		$this->assertEmpty(\Test_Helper::invokePrivate($this->manager, 'getShares', [null]), 'Asserting all shares for the user have been deleted');
	}

	/**
	 * @param array $expected
	 * @param array $actual
	 * @param int $share
	 * @param string $mountPoint
	 */
	protected function assertExternalShareEntry($expected, $actual, $share, $mountPoint) {
		$this->assertEquals($expected['remote'], $actual['remote'], 'Asserting remote of a share #' . $share);
		$this->assertEquals($expected['token'], $actual['share_token'], 'Asserting token of a share #' . $share);
		$this->assertEquals($expected['name'], $actual['name'], 'Asserting name of a share #' . $share);
		$this->assertEquals($expected['owner'], $actual['owner'], 'Asserting owner of a share #' . $share);
		$this->assertEquals($expected['accepted'], (int) $actual['accepted'], 'Asserting accept of a share #' . $share);
		$this->assertEquals($expected['user'], $actual['user'], 'Asserting user of a share #' . $share);
		$this->assertEquals($mountPoint, $actual['mountpoint'], 'Asserting mountpoint of a share #' . $share);

	}
}

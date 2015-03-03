<?php
/**
 * Copyright (c) 2015 Vincent Petry <pvince81@owncloud.com>
 * This file is licensed under the Affero General Public License version 3 or
 * later.
 * See the COPYING-README file.
 */

namespace OCA\Files_sharing\Command;

use OCP\Command\ICommand;
use OCP\IDBConnection;

/**
 * Delete all share entries that have no matching entries in the file cache table.
 */
class DeleteOrphanedSharesCommand implements ICommand {

	/**
     * {@inheritdoc}
	 */
	public function handle() {
		$connection = \OC::$server->getDatabaseConnection();
		// TODO: SQLite...
		$connection->executeUpdate(
			'DELETE `s` FROM `*PREFIX*share` `s` ' .
			'LEFT JOIN `*PREFIX*filecache` `f` ON `s`.`file_source`=`f`.`fileid` ' .
			'WHERE `f`.`fileid` IS NULL;'
		);
	}

}

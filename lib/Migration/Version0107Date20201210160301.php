<?php
/**
 * @copyright Copyright (c) 2017 René Gieling <github@dartcafe.de>
 *
 * @author René Gieling <github@dartcafe.de>
 *
 * @license GNU AGPL version 3 or any later version
 *
 *  This program is free software: you can redistribute it and/or modify
 *  it under the terms of the GNU Affero General Public License as
 *  published by the Free Software Foundation, either version 3 of the
 *  License, or (at your option) any later version.
 *
 *  This program is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU Affero General Public License for more details.
 *
 *  You should have received a copy of the GNU Affero General Public License
 *  along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 */

namespace OCA\Polls\Migration;

use OCP\DB\ISchemaWrapper;
use OCP\Migration\IOutput;
use OCP\IDBConnection;
use OCP\Migration\SimpleMigrationStep;
use Doctrine\DBAL\Schema\SchemaException;

class Version0107Date20201210160301 extends SimpleMigrationStep {

	/** @var IDBConnection */
	protected $connection;

	public function __construct(IDBConnection $connection) {
		$this->connection = $connection;
	}

	public function changeSchema(IOutput $output, \Closure $schemaClosure, array $options) {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();

		if ($schema->hasTable('polls_log')) {
			$table = $schema->getTable('polls_log');
			$table->changeColumn('poll_id', [
				'default' => 0
			]);
			$table->changeColumn('user_id', [
				'length' => 64,
				'notnull' => true,
				'default' => ''
			]);
			$table->changeColumn('message', [
				'length' => 128,
				'notnull' => true,
				'default' => ''
			]);
			$table->changeColumn('message_id', [
				'notnull' => true,
				'default' => ''
			]);

			try {
				$table->addUniqueIndex(['processed', 'poll_id', 'user_id', 'message_id', 'message'], 'UNIQ_unprocessed');
			} catch (SchemaException $e) {
				// catch silently, index is already present
			}
		}
		return $schema;
	}
}
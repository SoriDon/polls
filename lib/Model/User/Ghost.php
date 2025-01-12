<?php

declare(strict_types=1);
/**
 * @copyright Copyright (c) 2021 René Gieling <github@dartcafe.de>
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

namespace OCA\Polls\Model\User;

use OCA\Polls\Model\UserBase;

class Ghost extends UserBase {
	public const TYPE = 'deleted';
	public const ICON = 'icon-ghost';

	public function __construct(string $id) {
		parent::__construct($id, self::TYPE);
	}

	public function getDisplayName(): string {
		// TODO: Just a quick fix, differentiate anoymous and deleted users on userGroup base
		if (substr($this->getId(), 0, 9) === 'Anonymous') {
			return $this->getId();
		} else {
			return 'Deleted User';
		}
	}

}

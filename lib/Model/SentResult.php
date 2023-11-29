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

namespace OCA\Polls\Model;

class SentResult implements \JsonSerializable {
	public const INVALID_EMAIL_ADDRESS = 'InvalidMail';
	public const UNHANDELED_REASON = 'UnknownError';

	private array $sentMails = [];
	private array $abortedMails = [];

	public function AddSentMail(UserBase $recipient): void {
		array_push($this->sentMails, [
			'emailAddress' => $recipient->getEmailAddress(),
			'displayName' => $recipient->getDisplayName(),
		]);
	}

	public function AddAbortedMail(UserBase $recipient, string $reason = self::UNHANDELED_REASON): void {
		array_push($this->abortedMails, [
			'emailAddress' => $recipient->getEmailAddress(),
			'displayName' => $recipient->getDisplayName(),
			'reason' => $reason,
		]);
	}

	public function jsonSerialize(): array {
		return	[
			'sentMails' => $this->sentMails,
			'abortedMails' => $this->abortedMails,
			'countSentMails' => count($this->sentMails),
			'countAbortedMails' => count($this->abortedMails),
		];
	}
}

<?php

declare(strict_types=1);
/**
 * @copyright Copyright (c) 2023 René Gieling <github@dartcafe.de>
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

namespace OCA\Polls\Db;

use OCA\Polls\AppConstants;
use OCA\Polls\Exceptions\InvalidShareTypeException;
use OCA\Polls\Exceptions\ShareNotFoundException;
use OCA\Polls\Exceptions\UserNotFoundException;
use OCA\Polls\Model\Group\Circle;
use OCA\Polls\Model\Group\ContactGroup;
use OCA\Polls\Model\Group\Group;
use OCA\Polls\Model\User\Admin;
use OCA\Polls\Model\User\Contact;
use OCA\Polls\Model\User\Email;
use OCA\Polls\Model\User\GenericUser;
use OCA\Polls\Model\User\Ghost;
use OCA\Polls\Model\User\User;
use OCA\Polls\Model\UserBase;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\QBMapper;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;
use OCP\ISession;
use OCP\IUser;
use OCP\IUserManager;
use OCP\IUserSession;
use Psr\Log\LoggerInterface;

/**
 * @template-extends QBMapper<Share>
 *
 * This is a pseudo mapper for low level user operations to simplyfy unique handling of share users and nextcloud users
 */
class UserMapper extends QBMapper {
	public const TABLE = Share::TABLE;
	protected ?UserBase $currentUser = null;
	protected string $currentUserId;

	public function __construct(
		IDBConnection $db,
		protected ISession $session,
		protected IUserSession $userSession,
		protected IUserManager $userManager,
		protected LoggerInterface $logger,
	) {
		parent::__construct($db, Share::TABLE, Share::class);
	}

	/**
	 * Get current userId
	 *
	 * Returns userId of the current (share|nextcloud) user
	 **/
	public function getCurrentUserId(): string {
		$userId = $this->session->get(AppConstants::SESSION_KEY_USER_ID);
		return $userId ?? $this->getCurrentUser()->getId();
	}

	/**
	 * Get current user
	 *
	 * Returns a UserBase child for the current (share|nextcloud) user based on
	 * - the session stored share token or
	 * - the user session stored userId
	 * and stores userId to session
	 *
	 * !! The share is prioritised to tell User from Admin class
	 */
	public function getCurrentUser(): UserBase {
		if ($this->currentUser) {
			// if already loaded, return user
			return $this->currentUser;
		}

		$token = $this->session->get(AppConstants::SESSION_KEY_SHARE_TOKEN) ?? '';


		if ($this->isLoggedIn()) {
			$this->currentUser = $this->getUserFromUserBase($this->userSession->getUser()->getUID());
		} else {
			$this->currentUser = $this->getUserFromShareToken($token);
		}

		// store userId in session to avoid unecessary db access
		$this->session->set(AppConstants::SESSION_KEY_USER_ID, $this->currentUser->getId());
		return $this->currentUser;
	}

	public function isLoggedIn(): bool {
		return $this->userSession->isLoggedIn();
	}

	/**
	 * Get poll participant
	 *
	 * Returns a UserBase child from share determined by userId and pollId
	 *
	 * @param string $userId Get internal user. If pollId is given, the user who participates in the particulair poll will be returned
	 * @param int $pollId Can only be used together with $userId and will return the internal user or the share user
	 * @return UserBase
	 **/
	public function getParticipant(string $userId, int $pollId = null): UserBase {
		try {
			return $this->getUserFromUserBase($userId);
		} catch (UserNotFoundException $e) {
			// just catch and continue if not found and try to find user by share;
		}

		try {
			$share = $this->getShareByPollAndUser($userId, $pollId);
			return $this->getUserFromShare($share);
		} catch (ShareNotFoundException $e) {
			// User seems to be probaly deleted, use fake share
			return new Ghost($userId);
		}
	}

	/**
	 * Get participans of a poll as array of user objects
	 * @return UserBase[]
	 */
	public function getParticipants(int $pollId): array {
		$users = [];
		// get the distict list of usernames from the votes
		$participants = $this->findParticipantsByPoll($pollId);

		foreach ($participants as &$participant) {
			$users[] = $this->getParticipant($participant->getUserId(), $pollId);
		}
		return $users;
	}

	/**
	 * Get participans of a poll as array of user objects
	 *
	 * Returns a UserBase child build from a share
	 */
	public function getUserFromShare(Share $share): UserBase {
		return $this->getUserObject(
			$share->getType(),
			$share->getUserId(),
			$share->getDisplayName(),
			$share->getEmailAddress(),
			$share->getLanguage(),
			$share->getLocale(),
			$share->getTimeZoneName()
		);
	}

	public function getUserFromUserBase(string $userId): UserBase {
		$user = $this->userManager->get($userId);
		if ($user instanceof IUser) {
			return $this->getUserObject(User::TYPE, $userId);
		}
		throw new UserNotFoundException();
	}

	private function getUserFromShareToken(string $token):UserBase {
		$share = $this->getShareByToken($token);
		return $this->getUserFromShare($share);
	}

	public function getUserObject(string $type, string $id, string $displayName = '', ?string $emailAddress = '', string $language = '', string $locale = '', string $timeZoneName = ''): UserBase {
		return match ($type) {
			Ghost::TYPE => new Ghost($id),
			Group::TYPE => new Group($id),
			Circle::TYPE => new Circle($id),
			Contact::TYPE => new Contact($id),
			ContactGroup::TYPE => new ContactGroup($id),
			User::TYPE => new User($id),
			Admin::TYPE => new Admin($id),
			Email::TYPE => new Email($id, $displayName, $emailAddress, $language),
			UserBase::TYPE_PUBLIC => new GenericUser($id, UserBase::TYPE_PUBLIC),
			UserBase::TYPE_EXTERNAL => new GenericUser($id, UserBase::TYPE_EXTERNAL, $displayName, $emailAddress, $language, $locale, $timeZoneName),
			default => throw new InvalidShareTypeException('Invalid user type (' . $type . ')'),
		};
	}

	private function getShareByToken(string $token): Share {
		$qb = $this->db->getQueryBuilder();
	
		$qb->select('*')
			->from($this->getTableName())
			->where($qb->expr()->eq('token', $qb->createNamedParameter($token, IQueryBuilder::PARAM_STR)));
	
		return $this->findEntity($qb);
	}

	private function getShareByPollAndUser(string $userId, ?int $pollId = null): Share {
		$qb = $this->db->getQueryBuilder();

		$qb->select('*')
			->from($this->getTableName())
			->where($qb->expr()->eq('poll_id', $qb->createNamedParameter($pollId, IQueryBuilder::PARAM_INT)))
			->andWhere($qb->expr()->eq('user_id', $qb->createNamedParameter($userId, IQueryBuilder::PARAM_STR)));

		try {
			return $this->findEntity($qb);
		} catch (DoesNotExistException $e) {
			throw new ShareNotFoundException("Share not found by userId and pollId");
		}
	}

	private function findParticipantsByPoll(int $pollId): array {
		$qb = $this->db->getQueryBuilder();

		$qb->selectDistinct(['user_id', 'poll_id'])
			->from(Vote::TABLE)
			->where(
				$qb->expr()->eq('poll_id', $qb->createNamedParameter($pollId, IQueryBuilder::PARAM_INT))
			);

		return $this->findEntities($qb);
	}

}

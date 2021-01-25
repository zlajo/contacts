<?php
/**
 * @copyright Copyright (c) 2021 Johannes Zlattinger <me@zlajo.net>
 *
 * @author Johannes Zlattinger <me@zlajo.net>
 *
 * @license GNU AGPL version 3 or any later version
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 *
 */

namespace OCA\Contacts\Dav;

use OCA\Contacts\Cron\CategoryGroupSynchronizationJob;
use OCP\BackgroundJob\IJobList;
use Psr\Log\LoggerInterface;
use Sabre\DAV\Server;
use Sabre\DAV\ServerPlugin;
use Sabre\HTTP\RequestInterface;
use Sabre\HTTP\ResponseInterface;
use Sabre\VObject\UUIDUtil;
use Sabre\VObject\Reader;
use Sabre\VObject\Component\VCard;

class CategoryGroupSynchronizationPlugin extends ServerPlugin {

	/** @var LoggerInterface */
	protected $logger;

  /** @var Server */
	protected $server;

	public function __construct(LoggerInterface $logger) {
		$this->logger = $logger;
  }

	/**
	 * Initializes the plugin and registers event handlers
	 *
	 * @param Server $server
	 * @return void
	 */
	public function initialize(Server $server) {
		$this->server = $server;

		$server->on('afterMethod:PUT', [$this, 'synchronize']);
		$server->on('beforeMethod:DESTROY', [$this, 'synchronize']);
		$server->on('beforeMethod:DELETE', [$this, 'synchronize']);
	}

	public function synchronize(RequestInterface $request, ResponseInterface $response) {
		$method = $request->getMethod();
		$path = $request->getPath();
		$node = $this->server->tree->getNodeForPath($path);
		$card = Reader::read($node->get());

		$this->logger->error("Method: {method} - Path: {path}", ['method' => $method, 'path' => $path]);

		return true;
	}

	public function transferCategoriesToGroups(VCard $contact, array $groups): array {
		$categories = [];
		if (isset($contact->CATEGORIES)) {
			$categories = array_filter(
				explode(',', $contact->CATEGORIES->getValue()),
				fn($c) => trim($c) <> ""
			);
		}

		$groupNames = array_map(fn($g) => $g->FN->getValue(), $groups);

		$contactReference = "urn:uuid:".$contact->UID->getValue();

		$updatedGroups = [];

		foreach ($categories as $category) {
			if (!in_array($category, $groupNames)) {
				$rawGroup = implode("\r\n", [
					"BEGIN:VCARD",
		      "VERSION:3.0",
					"X-ADDRESSBOOKSERVER-KIND:group",
		      "UID:".UUIDUtil::getUUID(),
		      "FN:".$category,
					"X-ADDRESSBOOKSERVER-MEMBER:".$contactReference,
		      "END:VCARD"
				]);
				$updatedGroups[] = Reader::read($rawGroup);
			}
		}

		foreach ($groups as $group) {
			$groupName = $group->FN->getValue();
			$groupMembers = array_map(fn($g) => $g->getValue(), $group->select('X-ADDRESSBOOKSERVER-MEMBER'));

			if (in_array($groupName, $categories) && !in_array($contactReference, $groupMembers)) {
				$group->add('X-ADDRESSBOOKSERVER-MEMBER', $contactReference);

				$updatedGroups[] = $group;
			} else if (!in_array($groupName, $categories) && in_array($contactReference, $groupMembers)) {
				foreach ($group->select('X-ADDRESSBOOKSERVER-MEMBER') as $membership) {
					if ($membership->getValue() == $contactReference) {
						$group->remove($membership);

						$updatedGroups[] = $group;
					}
				}
			}
		}

		return $updatedGroups;
	}

	public function transferGroupToCategories(VCard $group, array $contacts): array {
		$groupName = $group->FN->getValue();
		$groupMembers = array_map(fn($g) => $g->getValue(), $group->select('X-ADDRESSBOOKSERVER-MEMBER'));

		$updatedContacts = [];

		foreach ($contacts as $contact) {
			$contactReference = "urn:uuid:".$contact->UID->getValue();

			$categories = $contact->CATEGORIES ? explode(',', $contact->CATEGORIES->getValue()) : [];

			if (in_array($contactReference, $groupMembers) && !in_array($groupName, $categories)) {
				$categories[] = $group->FN->getValue();

				$contact->CATEGORIES = implode(',', array_unique($categories));

				$updatedContacts[] = $contact;
			} else if (!in_array($contactReference, $groupMembers) && in_array($groupName, $categories)) {
				$contact->CATEGORIES = implode(',', array_filter($categories, fn($c) => $c != $groupName));

				$updatedContacts[] = $contact;
			}
		}

		return $updatedContacts;
	}

	public function deleteContactFromGroups(string $contactId, array $groups) {
		$contactReference = "urn:uuid:".$contactId;

		$updatedGroups = [];

		foreach ($groups as $group) {
			$groupMembers = array_map(fn($g) => $g->getValue(), $group->select('X-ADDRESSBOOKSERVER-MEMBER'));

			foreach ($group->select('X-ADDRESSBOOKSERVER-MEMBER') as $membership) {
				if ($membership->getValue() == $contactReference) {
					$group->remove($membership);

					$updatedGroups[] = $group;
				}
			}
		}

		return $updatedGroups;
	}

	public function deleteCategoryFromContacts(string $category, array $contacts) {
		$updatedContacts = [];

		foreach ($contacts as $contact) {
			$categories = $contact->CATEGORIES ? explode(',', $contact->CATEGORIES->getValue()) : [];

			if (in_array($category, $categories)) {
				$contact->CATEGORIES = implode(',', array_filter($categories, fn($c) => $c != $category));

				$updatedContacts[] = $contact;
			}
		}

		return $updatedContacts;
	}
}

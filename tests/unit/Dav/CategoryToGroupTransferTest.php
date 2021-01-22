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

use ChristophWurst\Nextcloud\Testing\TestCase;
use PHPUnit\Framework\MockObject\MockObject;

use Sabre\DAV\Server;
use Sabre\VObject\Reader;
use Sabre\VObject\Writer;
use Sabre\VObject\Component\VCard;

trait VCardTestUtilities {
  public function createContact(string $uid, string $fn, array $categories): VCard {
    $rawCard = implode("\r\n", [
      "BEGIN:VCARD",
      "VERSION:3.0",
      "UID:".$uid,
      "FN:".$fn,
      "CATEGORIES:".implode(",", $categories),
      "END:VCARD"
    ]);
    return Reader::read($rawCard);
  }

  public function createGroup(string $uid, string $fn, array $members = []): VCard {
    $rawCard = implode("\r\n", array_merge(
      [
        "BEGIN:VCARD",
        "VERSION:3.0",
        "X-ADDRESSBOOKSERVER-KIND:group",
        "UID:".$uid,
        "FN:".$fn
      ],
      array_map(fn($id) => "X-ADDRESSBOOKSERVER-MEMBER:urn:uuid:".$id, $members),
      [
        "END:VCARD"
      ]
    ));
    return Reader::read($rawCard);
  }
}

trait VCardTestAssertions {
  public function assertContainsGroup(string $groupName, array $groups) {
    $groupNames = array_map(fn($g) => $g->FN->getValue(), $groups);

    $this->assertContains($groupName, $groupNames);
  }

  public function assertNotContainsGroup(string $groupName, array $groups) {
    $groupNames = array_map(fn($g) => $g->FN->getValue(), $groups);

    $this->assertNotContains($groupName, $groupNames);
  }

  public function assertGroupContainsMember(string $groupName, string $member, array $groups) {
    $group = array_values(array_filter($groups, fn($g) => $g->FN->getValue() == $groupName))[0];

    $this->assertNotNull($group);

    $members = array_map(fn($g) => $g->getValue(), $group->select('X-ADDRESSBOOKSERVER-MEMBER'));

    $this->assertContains($member, $members);
  }

  public function assertNotGroupContainsMember(string $groupName, string $member, array $groups) {
    $group = array_values(array_filter($groups, fn($g) => $g->FN->getValue() == $groupName))[0];

    $this->assertNotNull($group);

    $members = array_map(fn($g) => $g->getValue(), $group->select('X-ADDRESSBOOKSERVER-MEMBER'));

    $this->assertNotContains($member, $members);
  }
}

class CategoryToGroupTransferTest extends TestCase {
  use VCardTestUtilities;
  use VCardTestAssertions;

  private $controller;

  private $server;

  protected function setUp(): void {
		parent::setUp();

		$this->server = $this->createMock(Server::class);

		$this->controller = new PatchPlugin();
    $this->controller->initialize($this->server);
	}

  public function testContactWithNoCategories() {
    $contact = $this->createContact("11111", "Anton Adler", []);

    $updatedGroups = $this->controller->transferCategoriesToGroups($contact, []);

    $this->assertEmpty($updatedGroups);
  }

  public function testContactWithOneNewCategory() {
    $contact = $this->createContact("11111", "Anton Adler", ["A"]);

    $updatedGroups = $this->controller->transferCategoriesToGroups($contact, []);

    $this->assertCount(1, $updatedGroups);
    $this->assertContainsGroup("A", $updatedGroups);
    $this->assertGroupContainsMember("A", "urn:uuid:11111", $updatedGroups);
  }

  public function testContactWithOneNewAndOneExistingCategory() {
    $contact = $this->createContact("11111", "Anton Adler", ["A", "B"]);
    $group = $this->createGroup("AAAAA", "A");

    $updatedGroups = $this->controller->transferCategoriesToGroups($contact, [$group]);

    $this->assertCount(2, $updatedGroups);
    $this->assertContainsGroup("A", $updatedGroups);
    $this->assertGroupContainsMember("A", "urn:uuid:11111", $updatedGroups);
    $this->assertContainsGroup("B", $updatedGroups);
    $this->assertGroupContainsMember("B", "urn:uuid:11111", $updatedGroups);
  }

  public function testContactWithCategoryWithFurtherMembers() {
    $contact = $this->createContact("11111", "Anton Adler", ["A"]);
    $group = $this->createGroup("AAAAA", "A", ["00001", "00002"]);

    $updatedGroups = $this->controller->transferCategoriesToGroups($contact, [$group]);

    $this->assertCount(1, $updatedGroups);
    $this->assertContainsGroup("A", $updatedGroups);
    $this->assertGroupContainsMember("A", "urn:uuid:11111", $updatedGroups);
    $this->assertGroupContainsMember("A", "urn:uuid:00001", $updatedGroups);
    $this->assertGroupContainsMember("A", "urn:uuid:00002", $updatedGroups);
  }

  public function testContactWithExistingGroupMembership() {
    $contact = $this->createContact("11111", "Anton Adler", ["A", "B"]);
    $groups = [
      $this->createGroup("AAAAA", "A", ["11111"]),
      $this->createGroup("BBBBB", "B", ["00001", "11111"])
    ];

    $updatedGroups = $this->controller->transferCategoriesToGroups($contact, $groups);

    $this->assertEmpty($updatedGroups);
  }

  public function testContactWithExistingAndNewGroupMembership() {
    $contact = $this->createContact("11111", "Anton Adler", ["A", "B", "C"]);
    $groups = [
      $this->createGroup("AAAAA", "A", ["11111"]),
      $this->createGroup("BBBBB", "B", [])
    ];

    $updatedGroups = $this->controller->transferCategoriesToGroups($contact, $groups);

    $this->assertCount(2, $updatedGroups);

    $this->assertNotContainsGroup("A", $updatedGroups);

    $this->assertContainsGroup("B", $updatedGroups);
    $this->assertGroupContainsMember("B", "urn:uuid:11111", $updatedGroups);

    $this->assertContainsGroup("C", $updatedGroups);
    $this->assertGroupContainsMember("C", "urn:uuid:11111", $updatedGroups);
  }

  public function testRemoveContactCategory() {
    $contact = $this->createContact("11111", "Anton Adler", []);
    $groups = [
      $this->createGroup("AAAAA", "A", ["00001", "11111"])
    ];

    $updatedGroups = $this->controller->transferCategoriesToGroups($contact, $groups);

    $this->assertContainsGroup("A", $updatedGroups);
    $this->assertGroupContainsMember("A", "urn:uuid:00001", $updatedGroups);
    $this->assertNotGroupContainsMember("A", "urn:uuid:11111", $updatedGroups);
  }
}

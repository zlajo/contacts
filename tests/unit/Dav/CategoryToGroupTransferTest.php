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

use Psr\Log\LoggerInterface;
use Sabre\DAV\Server;

include_once 'VCardTestUtilities.php';
include_once 'VCardTestAssertions.php';

class CategoryToGroupTransferTest extends TestCase {
  use \VCardTestUtilities;
  use \VCardTestAssertions;

  private $logger;

  private $server;

  private $controller;

  protected function setUp(): void {
		parent::setUp();

    $this->logger = $this->createMock(LoggerInterface::class);
		$this->server = $this->createMock(Server::class);

		$this->controller = new CategoryGroupSynchronizationPlugin($this->logger);
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

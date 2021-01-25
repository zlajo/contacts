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

class DeletedGroupContactSynchronizationTest extends TestCase {
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

  public function testDeleteCategoryFromEmptyContacts() {
    $updatedContacts = $this->controller->deleteCategoryFromContacts('A', []);

    $this->assertEmpty($updatedContacts);
  }

  public function testDeleteCategoryWithNoAssociatedContacts() {
    $contacts = [
      $this->createContact("11111", "Anton Adler", ['A']),
      $this->createContact("22222", "Benjamin Bachmann", ['B'])
    ];

    $updatedContacts = $this->controller->deleteCategoryFromContacts('X', $contacts);

    $this->assertEmpty($updatedContacts);
  }

  public function testDeleteCategoryWithOneAssociatedContact() {
    $contacts = [
      $this->createContact("11111", "Anton Adler", ['A']),
      $this->createContact("22222", "Benjamin Bachmann", ['B'])
    ];

    $updatedContacts = $this->controller->deleteCategoryFromContacts('A', $contacts);

    $this->assertContainsContact("Anton Adler", $updatedContacts);
    $this->assertNotContactContainsCategory("Anton Adler", "A", $updatedContacts);
  }
}

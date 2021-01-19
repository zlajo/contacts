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
use Sabre\VObject\Component\VCard;


class CategoryToGroupTransferTest extends TestCase {
  private $controller;

  private $server;

  protected function setUp(): void {
		parent::setUp();

		$this->server = $this->createMock(Server::class);

		$this->controller = new PatchPlugin();
    $this->controller->initialize($this->server);
	}

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

  public function testContactWithNoCategories() {
    $contact = $this->createContact("11111", "Anton Adler", []);

    $updatedGroups = $this->controller->transferCategoriesToGroups($contact, []);

    $this->assertEmpty($updatedGroups);
  }

  public function testContactWithOneNewCategory() {
    $contact = $this->createContact("11111", "Anton Adler", ["A"]);

    $updatedGroups = $this->controller->transferCategoriesToGroups($contact, []);

    $this->assertCount(1, $updatedGroups);
    $this->assertEquals("A", $updatedGroups[0]->{'FN'}->getValue());
    $this->assertEquals("urn:uuid:11111", $updatedGroups[0]->{'X-ADDRESSBOOKSERVER-MEMBER'}->getValue());
  }

  public function testContactWithOneNewAndOneExistingCategory() {
    $contact = $this->createContact("11111", "Anton Adler", ["A", "B"]);
    $group = $this->createGroup("AAAAA", "A");

    $updatedGroups = $this->controller->transferCategoriesToGroups($contact, [$group]);

    $this->assertCount(2, $updatedGroups);
    $this->assertContains("A", array_map(fn($g) => $g->FN->getValue(), $updatedGroups));
    $this->assertContains("B", array_map(fn($g) => $g->FN->getValue(), $updatedGroups));

    $groupA = array_values(array_filter($updatedGroups, fn($g) => $g->FN->getValue() == "A"))[0];
    $groupB = array_values(array_filter($updatedGroups, fn($g) => $g->FN->getValue() == "B"))[0];

    // fwrite(STDOUT, print_r($groupA, TRUE));

    $this->assertEquals("urn:uuid:11111", $groupA->{'X-ADDRESSBOOKSERVER-MEMBER'}->getValue());
    $this->assertEquals("urn:uuid:11111", $groupB->{'X-ADDRESSBOOKSERVER-MEMBER'}->getValue());
  }

  public function testContactWithCategoryWithFurtherMembers() {
    $contact = $this->createContact("11111", "Anton Adler", ["A"]);
    $group = $this->createGroup("AAAAA", "A", ["00001", "00002"]);

    $updatedGroups = $this->controller->transferCategoriesToGroups($contact, [$group]);

    $groupA = array_values(array_filter($updatedGroups, fn($g) => $g->FN->getValue() == "A"))[0];

    // fwrite(STDOUT, print_r(
    //   array_map(fn($p) => $p->getValue(), $group->select('X-ADDRESSBOOKSERVER-MEMBER')),
    //   TRUE));

    $this->assertContains("urn:uuid:11111", array_map(fn($p) => $p->getValue(), $groupA->select('X-ADDRESSBOOKSERVER-MEMBER')));
    $this->assertContains("urn:uuid:00001", array_map(fn($p) => $p->getValue(), $groupA->select('X-ADDRESSBOOKSERVER-MEMBER')));
    $this->assertContains("urn:uuid:00002", array_map(fn($p) => $p->getValue(), $groupA->select('X-ADDRESSBOOKSERVER-MEMBER')));
  }
}

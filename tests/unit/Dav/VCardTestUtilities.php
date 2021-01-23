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

use Sabre\VObject\Reader;
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

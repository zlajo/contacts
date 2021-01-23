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

   public function assertContainsContact(string $contactName, array $contacts) {
     $contactNames = array_map(fn($g) => $g->FN->getValue(), $contacts);

     $this->assertContains($contactName, $contactNames);
   }

   public function assertContactContainsCategory(string $contactName, string $category, array $contacts) {
     $contact = array_values(array_filter($contacts, fn($g) => $g->FN->getValue() == $contactName))[0];

     $this->assertNotNull($contact);

     $categories = explode(',', $contact->CATEGORIES->getValue());

     $this->assertContains($category, $categories);
   }
 }

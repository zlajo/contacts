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

// import ICAL from 'ical.js'
import { namespaces as NS } from 'cdav-library'

import Contact from '../models/contact'

const VCARD_PROPERTIES_MEMBER = 'x-addressbookserver-member'

const VCARD_UID_PREFIX = 'urn:uuid:'

const vCardMixin = {
	getAllProperties(name) {
		return this.vCard.getAllProperties().filter(p => p.name === name.toLowerCase())
	},

	addPropertyWithValue(name, value) {
		this.vCard.addPropertyWithValue(name, value)
	},

	removeProperty(property) {
		this.vCard.removeProperty(property)
	},
}

async function findGroupByName(addressbook, groupName) {
	return addressbook.dav.addressbookQuery([
		{
			name: [NS.IETF_CARDDAV, 'prop-filter'],
			attributes: [
				['name', 'FN'],
			],
			children: [{
				name: [NS.IETF_CARDDAV, 'text-match'],
				attributes: [
					['collation', 'i;unicode-casemap'],
					['match-type', 'equals'],
				],
				value: groupName,
			}],
		},
	]).then(function(cards) {
		return cards
			.map(function(dav) {
				const card = new Contact(dav.data, addressbook)
				Object.assign(card, vCardMixin)
				card.dav = dav
				return card
			}).find(function(c) {
				return c.kind.toLowerCase() === 'group'
			})
	})
}

async function createGroup(addressbook, groupName) {
	const contactGroup = new Contact(`
		BEGIN:VCARD
		VERSION:3.0
		X-ADDRESSBOOKSERVER-KIND:group
		PRODID:-//Nextcloud Contacts v${appVersion}
		N:;${groupName};;;
		FN:${groupName}
		END:VCARD
	`.trim().replace(/\t/gm, ''),
	addressbook)

	return addressbook.dav.createVCard(contactGroup.vCard.toString())
		.then(function(dav) {
			const card = new Contact(dav.data, addressbook)
			Object.assign(card, vCardMixin)
			card.dav = dav
			return card
		})
}

async function findOrCreateGroup(addressbook, { name }) {
	return new Promise(function(resolve, reject) {
		findGroupByName(addressbook, name).then(function(group) {
			if (group) {
				resolve(group)
			} else {
				createGroup(addressbook, name).then(function(group) {
					resolve(group)
				}).catch((error) => {
					reject(error)
				})
			}
		})
	})
}

async function addContactToGroup(addressbook, group, contact) {
	return findOrCreateGroup(contact.addressbook, group).then(function(group) {
		if (group.getAllProperties(VCARD_PROPERTIES_MEMBER).length <= 0) {
			group.addPropertyWithValue(VCARD_PROPERTIES_MEMBER, VCARD_UID_PREFIX + contact.uid)
			group.dav.data = group.vCard.toString()
			group.dav.update()
		}

		return { group, contact }
	})
}

async function removeContactFromGroup(addressbook, group, contact) {
	return findGroupByName(contact.addressbook, group.name).then(function(group) {
		group.getAllProperties(VCARD_PROPERTIES_MEMBER)
			.filter(function(p) {
				return p.getValues().map(v => v.toLowerCase()).some(v => v === (VCARD_UID_PREFIX + contact.uid).toLowerCase())
			}).forEach(function(p) {
				group.removeProperty(p)
			})

		if (group.getAllProperties(VCARD_PROPERTIES_MEMBER).length > 0) {
			group.dav.data = group.vCard.toString()
			group.dav.update()
		} else {
			group.dav.delete()
		}

		return contact
	})
}

export default {
	findGroupByName,
	createGroup,
	findOrCreateGroup,
	addContactToGroup,
	removeContactFromGroup,
}

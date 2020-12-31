/**
 * @copyright Copyright (c) 2018 John Molakvoæ <skjnldsv@protonmail.com>
 *
 * @author John Molakvoæ <skjnldsv@protonmail.com>
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

import Vue from 'vue'
import { namespaces as NS } from 'cdav-library'

import Contact from '../models/contact'

async function findOrCreateGroup(groupName, addressbook) {
	return new Promise(function(resolve, reject) {
		findGroupByName(groupName, addressbook).then(function(group) {
			if (group) {
				resolve(group)
			} else {
				createGroup(groupName, addressbook).then(function(group) {
					resolve(group)
				}).catch((error) => {
					reject(error)
				})
			}
		}).catch((error) => {
			reject(error)
		})
	})
}

async function findGroupByName(groupName, addressbook) {
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
			.map(function(c) {
				const card = new Contact(c.data, addressbook)
				Vue.set(card, 'dav', c)
				return card
			}).find(function(c) {
				return c.kind.toLowerCase() === 'group'
			})
	}).catch((error) => {
		console.error(error)

		throw error
	})
}

async function createGroup(groupName, addressbook) {
	const contactGroup = new Contact(`
		BEGIN:VCARD
		VERSION:3.0
		X-ADDRESSBOOKSERVER-KIND:group
		PRODID:-//Nextcloud Contacts v${appVersion}
		N:${groupName};;;;
		FN:${groupName}
		END:VCARD
	`.trim().replace(/\t/gm, ''),
	addressbook)

	return addressbook.dav.createVCard(contactGroup.vCard.toString())
		.then(function(c) {
			const card = new Contact(c.data, addressbook)
			Vue.set(card, 'dav', c)
			return card
		}).catch((error) => {
			console.error(error)

			throw error
		})
}

const state = {
	groups: [],
}

const mutations = {
	/**
	 * Extract all the groups from the provided contacts
	 * and add the contacts to their respective groups
	 *
	 * @param {Object} state the store data
	 * @param {Contact[]} contacts the contacts to add
	 */
	extractGroupsFromContacts(state, contacts) {
		// iterate contacts
		contacts.forEach(contact => {
			if (contact.groups) {
				contact.groups.forEach(groupName => {
					let group = state.groups.find(search => search.name === groupName)
					// nothing? create a new one
					if (!group) {
						state.groups.push({
							name: groupName,
							contacts: [],
						})
						group = state.groups.find(search => search.name === groupName)
					}
					group.contacts.push(contact.key)
				})
			}
		})
	},

	/**
	 * Add contact to group and create groupif not existing
	 *
	 * @param {Object} state the store data
	 * @param {Object} data destructuring object
	 * @param {Array<string>} data.groupNames the names of the group
	 * @param {Contact} data.contact the contact
	 */
	addContactToGroups(state, { groupNames, contact }) {
		groupNames.forEach(groupName => {
			let group = state.groups.find(search => search.name === groupName)
			// nothing? create a new one
			if (!group) {
				state.groups.push({
					name: groupName,
					contacts: [],
				})
				group = state.groups.find(search => search.name === groupName)
			}

			group.contacts.push(contact.key)

			findOrCreateGroup(groupName, contact.addressbook).then(function(group) {
				group.vCard.addPropertyWithValue('X-ADDRESSBOOKSERVER-MEMBER', 'urn:uuid:' + contact.uid)
				group.dav.data = group.vCard.toString()
				group.dav.update()
			})
		})
	},

	/**
	 * Remove contact from group
	 *
	 * @param {Object} state the store data
	 * @param {Object} data destructuring object
	 * @param {string} data.groupName the name of the group
	 * @param {Contact} data.contact the contact
	 */
	removeContactToGroup(state, { groupName, contact }) {
		const contacts = state.groups.find(search => search.name === groupName).contacts
		const index = contacts.findIndex(search => search === contact.key)
		if (index > -1) {
			contacts.splice(index, 1)
		}

		findGroupByName(groupName, contact.addressbook).then(function(group) {
			if (group) {
				group.vCard.getAllProperties('x-addressbookserver-member')
					.filter(function(p) {
						return p.jCal[3] === 'urn:uuid:' + contact.uid
					}).forEach(function(p) {
						group.vCard.removeProperty(p)
					})

				if (group.vCard.getAllProperties('x-addressbookserver-member').length > 0) {
					group.dav.data = group.vCard.toString()
					group.dav.update()
				} else {
					group.dav.delete()
				}
			}
		})
	},

	/**
	 * Remove contact from its groups
	 *
	 * @param {Object} state the store data
	 * @param {Contact} contact the contact
	 */
	removeContactFromGroups(state, contact) {
		state.groups.forEach(group => {
			const index = group.contacts.indexOf(contact.key)
			if (index !== -1) {
				group.contacts.splice(index, 1)

				findGroupByName(group.name, contact.addressbook).then(function(group) {
					if (group) {
						group.vCard.getAllProperties('x-addressbookserver-member')
							.filter(function(p) {
								return p.jCal[3] === 'urn:uuid:' + contact.uid
							}).forEach(function(p) {
								group.vCard.removeProperty(p)
							})

						if (group.vCard.getAllProperties('x-addressbookserver-member').length > 0) {
							group.dav.data = group.vCard.toString()
							group.dav.update()
						} else {
							group.dav.delete()
						}
					}
				})
			}
		})
	},

	/**
	 * Add a group
	 *
	 * @param {Object} state the store data
	 * @param {string} groupName the name of the group
	 */
	addGroup(state, groupName) {
		state.groups.push({
			name: groupName,
			contacts: [],
		})
	},
}

const getters = {
	getGroups: state => state.groups,
}

const actions = {

	/**
	 * Add contact and to a group
	 *
	 * @param {Object} context the store mutations
	 * @param {Object} data destructuring object
	 * @param {string} data.groupName the name of the group
	 * @param {Contact} data.contact the contact
	 */
	addContactToGroup(context, { groupName, contact }) {
		context.commit('addContactToGroups', { groupNames: [groupName], contact })
	},

	/**
	 * Remove contact from its groups
	 *
	 * @param {Object} context the store mutations
	 * @param {Contact} contact the contact
	 */
	removeContactFromGroups(context, contact) {
		context.commit('removeContactFromGroups', contact)
	},

	/**
	 * Remove contact from group
	 *
	 * @param {Object} context the store mutations
	 * @param {Object} data destructuring object
	 * @param {string} data.groupName the name of the group
	 * @param {Contact} data.contact the contact
	 */
	removeContactToGroup(context, { groupName, contact }) {
		context.commit('removeContactToGroup', { groupName, contact })
	},

	/**
	 * Add a group
	 *
	 * @param {Object} context the store mutations
	 * @param {string} groupName the name of the group
	 */
	addGroup(context, groupName) {
		context.commit('addGroup', groupName)
	},
}

export default { state, mutations, getters, actions }

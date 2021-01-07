const Config = require('../config')

const { Builder, By, Key, until } = require('selenium-webdriver');
const { expect } = require('chai');
const path = require('path');

function sleep(ms) {
  return new Promise(resolve => setTimeout(resolve, ms));
}

describe('Contact Groups', () => {
  let driver = new Builder().forBrowser('firefox').build()

  before(async () => {
    await login()
    await reset()
  })

  after(async () => {
    await reset()

    driver.quit()
  })

  async function login() {
    await driver.get(path.join(Config.NextcloudBaseUrl, '/login'))
    const loginForm = await driver.findElement(By.name('login'))
    await loginForm.findElement(By.name('user')).sendKeys(Config.Username)
    await loginForm.findElement(By.name('password')).sendKeys(Config.Password)
    await loginForm.findElement(By.css('input[type="submit"]')).click()

    const url = await driver.getCurrentUrl()

    if (!url.match(/\/apps\/dashboard\/?/)) {
      throw new Error("Login failed!")
    }
  }

  async function reset() {
    await driver.get(path.join(Config.NextcloudBaseUrl, '/apps/contacts/All contacts'))

    for (let title of await getAddressbookTitles()) {
      await deleteAddressbook(title)
    }
  }

  async function getAddressbookTitles() {
    let settingsButton = await driver.wait(until.elementLocated(By.css('#app-settings-header button')))
    if (await settingsButton.isDisplayed()) {
      await settingsButton.click()
      await settingsButton.click()
    } else {
      await settingsButton.click()
    }

    return Promise.all(
      (await driver.wait(until.elementsLocated(By.css('#addressbook-list li .icon-shared'))))
      .map((element) => element.findElement(By.xpath('./parent::li/span')).getAttribute('title'))
    )
  }

  async function deleteAddressbook(title) {
    let settingsButton = await driver.wait(until.elementLocated(By.css('#app-settings-header button')))
    await settingsButton.click()

    let addressbookMenuButton = await driver.wait(
      until.elementLocated(By.css('#addressbook-list li *[title="' + title + '"] ~ .action-item button'))
    )
    await addressbookMenuButton.click()

    let actionIcon = await driver.wait(until.elementLocated(By.css('.popover .action-button .icon-delete')))
    await actionIcon.findElement(By.xpath('./parent::button')).click()

    let confirmationButton = await driver.wait(until.elementLocated(By.css('.oc-dialog button.primary')))

    await confirmationButton.click()
  }

  it('should create contact with single contact group', async () => {
    await driver.get(path.join(Config.NextcloudBaseUrl, '/apps/contacts/All contacts'))

    await createContact({fullname: 'Anton Aichinger'})

    await addContactToGroup('A')

    let contactTitles = await getGroupContacts('A')

    expect(contactTitles).to.eql(['Anton Aichinger'])
  })

  async function createContact(fields) {
    await driver.wait(until.elementLocated(By.css('#new-contact-button')))

    await driver.findElement(By.css('#new-contact-button')).click()

    await driver.wait(until.elementLocated(By.css('#contact-fullname')))
    await driver.findElement(By.css('#contact-fullname')).click()
    await driver.findElement(By.css('#contact-fullname')).sendKeys(fields.fullname)

    await driver.wait(until.stalenessOf(driver.findElement(By.css('.contact-header__actions .icon-error'))))
  }

  async function addContactToGroup(groupName) {
    let groupSelector = await driver.findElement(By.css('.property--groups input.multiselect__input'))
    await groupSelector.click()
    await groupSelector.sendKeys(groupName, Key.ENTER)

    await driver.wait(until.elementLocated(By.css('.app-navigation-entry a[href="/apps/contacts/' + groupName + '"]')))
  }

  async function getGroupContacts(groupName) {
    let contactGroup = await driver.wait(until.elementLocated(By.css('.app-navigation-entry a[href="/apps/contacts/' + groupName + '"]')))
    await contactGroup.click()

    await driver.wait(until.elementLocated(By.css('#contacts-list')))

    return await Promise.all(
      (await driver.findElements(By.css('#contacts-list .app-content-list-item-line-one')))
      .map((element) => element.getText())
    )
  }
})

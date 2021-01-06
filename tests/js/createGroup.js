const { Builder, By, Key, until } = require('selenium-webdriver');
const { expect } = require('chai');

function sleep(ms) {
  return new Promise(resolve => setTimeout(resolve, ms));
}

describe('DefaultTest', () => {
  let driver = new Builder().forBrowser('firefox').build()

  before(async () => {
    await login()
    await reset()
  })

  async function login() {
    await driver.get('https://local.zlattinger.net/login')
    const loginForm = await driver.findElement(By.name('login'))
    await loginForm.findElement(By.name('user')).sendKeys('zlajo')
    await loginForm.findElement(By.name('password')).sendKeys('password')
    await loginForm.findElement(By.css('input[type="submit"]')).click()

    const url = await driver.getCurrentUrl()

    if (!url.match(/\/apps\/dashboard\/?/)) {
      throw new Error("Login failed!")
    }
  }

  async function reset() {
    await driver.get('https://local.zlattinger.net/apps/contacts/All contacts')

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

  after(async () => {
    driver.quit()
  })

  it('should open Nextcloud', async () => {
    // await driver.get('https://local.zlattinger.net/apps/contacts/All contacts')
    //
    // const title = await driver.getTitle()
    //
    // console.log(await driver.getCurrentUrl())
    //
    // expect(title).to.equal('Contacts - Nextcloud')
  })
})

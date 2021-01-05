const { Builder, By, Key, until } = require('selenium-webdriver');
const { expect } = require('chai');

function sleep(ms) {
  return new Promise(resolve => setTimeout(resolve, ms));
}

describe('DefaultTest', () => {
  var driver = new Builder().forBrowser('firefox').build()

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
    await driver.wait(until.elementLocated(By.css('#app-settings-header')))

    await driver.findElement(By.css('#app-settings-header button')).click()

    return Promise.all(
      (await driver.findElements(By.css('#addressbook-list li .icon-shared')))
      .map((element) => element.findElement(By.xpath('./parent::li/span')).getAttribute('title'))
    )
  }

  async function deleteAddressbook(title) {
    await driver.wait(until.elementLocated(By.css('#app-settings-header')))

    await driver.findElement(By.css('#app-settings-header button')).click()

    await driver.findElement(By.css('#addressbook-list li *[title="' + title + '"] ~ .action-item button')).click()

    await driver.wait(until.elementLocated(By.css('.popover')))

    var actionIcon = await driver.findElement(By.css('.popover .action-button .icon-delete'))
    await actionIcon.findElement(By.xpath('./parent::button')).click()

    await driver.wait(until.elementLocated(By.css('.oc-dialog')))

    await driver.findElement(By.css('.oc-dialog button.primary')).click()
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

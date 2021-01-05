const { Builder, By, Key, until } = require('selenium-webdriver');
const { expect } = require('chai');

describe('DefaultTest', () => {
  var driver = new Builder().forBrowser('firefox').build()

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

    await driver.wait(until.elementLocated(By.css('#app-settings-header')))

    await driver.findElement(By.css('#app-settings-header button')).click()

    try {
      var addressbookActionItem = await driver.findElement(By.css('#addressbook-list li .icon-shared ~ .action-item button'))

      while(addressbookActionItem) {
        await addressbookActionItem.click()

        await driver.wait(until.elementLocated(By.css('.popover')))

        var actionIcon = await driver.findElement(By.css('.popover .action-button .icon-delete'))
        var actionButton = await actionIcon.findElement(By.xpath('./parent::button'))
        await actionButton.click()

        await driver.wait(until.elementLocated(By.css('.oc-dialog')))

        await driver.findElement(By.css('.oc-dialog button.primary')).click()

        await driver.wait(until.elementLocated(By.css('#app-settings-header')))

        await driver.findElement(By.css('#app-settings-header button')).click()

        addressbookActionItem = await driver.findElement(By.css('#addressbook-list li .icon-shared ~ .action-item button'))
      }
    } catch (e) {
      if (e.name != 'NoSuchElementError') {
        throw e
      }
    }
  }

  before(async () => {
    await login()
    await reset()
  })

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

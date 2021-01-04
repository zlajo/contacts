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

  before(async () => {
    await login()
  })

  after(async () => {
    driver.quit()
  })

  it('should open Nextcloud', async () => {
    await driver.get('https://local.zlattinger.net/apps/contacts/All contacts')

    const title = await driver.getTitle()

    console.log(await driver.getCurrentUrl())

    expect(title).to.equal('Contacts - Nextcloud')
  })
})

const { chromium, devices } = require('playwright');

(async () => {
  const browser = await chromium.launch();

  async function shoot(url, path, viewportOrDevice, fullPage) {
    const context = viewportOrDevice.userAgent
      ? await browser.newContext({ ...viewportOrDevice })
      : await browser.newContext({ viewport: viewportOrDevice });
    const page = await context.newPage();
    await page.route('**/*', route => {
      const headers = { ...route.request().headers(), 'Cache-Control': 'no-cache', 'Pragma': 'no-cache' };
      route.continue({ headers });
    });
    await page.goto(url + '?nocache=' + Date.now(), { waitUntil: 'networkidle', timeout: 30000 });
    await page.waitForTimeout(1500);
    await page.screenshot({ path, fullPage: !!fullPage });
    console.log('Saved ' + path);
    await context.close();
  }

  const iphone = devices['iPhone 13'];
  const desktop = { width: 1400, height: 1000 };

  await shoot('https://lunacibarcelona.com/contact/', 'contact-mobile-top.png', iphone, false);
  await shoot('https://lunacibarcelona.com/contact/', 'contact-desktop-top.png', desktop, false);
  await shoot('https://lunacibarcelona.com/contact/', 'contact-mobile-fullpage.png', iphone, true);

  await shoot('https://lunacibarcelona.com/es/about-us-es/', 'about-es-mobile-fullpage.png', iphone, true);
  await shoot('https://lunacibarcelona.com/es/about-us-es/', 'about-es-desktop-fullpage.png', desktop, true);
  await shoot('https://lunacibarcelona.com/about-us/', 'about-en-desktop-top.png', desktop, false);

  await browser.close();
})();

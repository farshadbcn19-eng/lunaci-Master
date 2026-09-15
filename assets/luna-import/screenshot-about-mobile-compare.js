const { chromium, devices } = require('playwright');

(async () => {
  const browser = await chromium.launch();
  const iphone = devices['iPhone 13'];

  for (const [label, url, file] of [
    ['EN', 'https://lunacibarcelona.com/about-us/', 'about-en-mobile-fullpage.png'],
    ['ES', 'https://lunacibarcelona.com/es/about-us-es/', 'about-es-mobile-fullpage-2.png'],
  ]) {
    const context = await browser.newContext({ ...iphone });
    const page = await context.newPage();
    await page.route('**/*', route => {
      const headers = { ...route.request().headers(), 'Cache-Control': 'no-cache', 'Pragma': 'no-cache' };
      route.continue({ headers });
    });
    await page.goto(url + '?nocache=' + Date.now(), { waitUntil: 'networkidle', timeout: 30000 });
    await page.waitForTimeout(1500);
    const height = await page.evaluate(() => document.body.scrollHeight);
    console.log(label + ' about-us mobile body.scrollHeight (CSS px): ' + height);
    await page.screenshot({ path: file, fullPage: true });
    console.log('Saved ' + file);
    await context.close();
  }

  await browser.close();
})();

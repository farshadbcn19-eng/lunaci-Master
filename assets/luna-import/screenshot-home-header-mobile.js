const { chromium, devices } = require('playwright');

(async () => {
  const browser = await chromium.launch();

  const iphone = devices['iPhone 13'];
  const context = await browser.newContext({ ...iphone });
  const page = await context.newPage();
  await page.route('**/*', route => {
    const headers = { ...route.request().headers(), 'Cache-Control': 'no-cache', 'Pragma': 'no-cache' };
    route.continue({ headers });
  });
  await page.goto('https://lunacibarcelona.com/?nocache=' + Date.now(), { waitUntil: 'networkidle', timeout: 30000 });
  await page.waitForTimeout(1500);

  await page.screenshot({ path: 'home-mobile-top.png' });
  console.log('Saved home-mobile-top.png');

  const navEl = await page.$('.ln-nav, #lnNav, #lunaciGlobalNav');
  if (navEl) {
    await navEl.screenshot({ path: 'home-mobile-nav-crop.png' });
    console.log('Saved home-mobile-nav-crop.png');
  } else {
    console.log('WARNING: nav element not found on home page');
  }

  const desktopContext = await browser.newContext({ viewport: { width: 1400, height: 1000 } });
  const desktopPage = await desktopContext.newPage();
  await desktopPage.route('**/*', route => {
    const headers = { ...route.request().headers(), 'Cache-Control': 'no-cache', 'Pragma': 'no-cache' };
    route.continue({ headers });
  });
  await desktopPage.goto('https://lunacibarcelona.com/?nocache=' + Date.now(), { waitUntil: 'networkidle', timeout: 30000 });
  await desktopPage.waitForTimeout(1500);
  await desktopPage.screenshot({ path: 'home-desktop-top.png' });
  console.log('Saved home-desktop-top.png');

  await browser.close();
})();

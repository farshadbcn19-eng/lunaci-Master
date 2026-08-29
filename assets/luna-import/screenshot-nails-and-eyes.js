const { chromium } = require('playwright');

(async () => {
  const browser = await chromium.launch();

  const nailsPage = await browser.newPage({ viewport: { width: 1400, height: 1000 } });
  await nailsPage.route('**/*', route => {
    const headers = { ...route.request().headers(), 'Cache-Control': 'no-cache', 'Pragma': 'no-cache' };
    route.continue({ headers });
  });
  await nailsPage.goto('https://lunacibarcelona.com/product-category/nails/?nocache=' + Date.now(), { waitUntil: 'networkidle', timeout: 30000 });
  const bannerEl = await nailsPage.$('.lunaci-category-banner');
  if (bannerEl) {
    await bannerEl.screenshot({ path: 'nails-banner-crop.png' });
    console.log('Saved nails-banner-crop.png');
  } else {
    console.log('WARNING: banner element not found on nails page');
  }
  await nailsPage.screenshot({ path: 'nails-category-fullpage.png', fullPage: true });
  console.log('Saved nails-category-fullpage.png');

  const eyesPage = await browser.newPage({ viewport: { width: 1400, height: 1000 } });
  await eyesPage.route('**/*', route => {
    const headers = { ...route.request().headers(), 'Cache-Control': 'no-cache', 'Pragma': 'no-cache' };
    route.continue({ headers });
  });
  await eyesPage.goto('https://lunacibarcelona.com/product-category/eyes/?nocache=' + Date.now(), { waitUntil: 'networkidle', timeout: 30000 });
  await eyesPage.screenshot({ path: 'eyes-category-fullpage.png', fullPage: true });
  console.log('Saved eyes-category-fullpage.png');

  await browser.close();
})();

const { chromium, devices } = require('playwright');

const pages = [
  { url: 'https://lunacibarcelona.com/es/productos/', name: 'products-es-mobile', device: 'iPhone 13' },
  { url: 'https://lunacibarcelona.com/contact/', name: 'contact-en-mobile', device: 'iPhone 13' },
  { url: 'https://lunacibarcelona.com/es/contacto/', name: 'contact-es-mobile', device: 'iPhone 13' },
  { url: 'https://lunacibarcelona.com/products/', name: 'products-en-mobile', device: 'iPhone 13' },
];

(async () => {
  const browser = await chromium.launch();

  for (const p of pages) {
    const context = await browser.newContext({ ...devices[p.device] });
    const page = await context.newPage();
    await page.route('**/*', route => {
      const headers = { ...route.request().headers(), 'Cache-Control': 'no-cache', 'Pragma': 'no-cache' };
      route.continue({ headers });
    });
    await page.goto(p.url + '?nocache=' + Date.now(), { waitUntil: 'networkidle', timeout: 30000 });
    await page.waitForTimeout(1500);

    await page.screenshot({ path: `${p.name}-top.png` });
    console.log(`Saved ${p.name}-top.png`);

    await page.screenshot({ path: `${p.name}-left-edge.png`, clip: { x: 0, y: 0, width: 40, height: 800 } });
    console.log(`Saved ${p.name}-left-edge.png`);

    await context.close();
  }

  await browser.close();
})();

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
  await page.goto('https://lunacibarcelona.com/es/productos/?nocache=' + Date.now(), { waitUntil: 'networkidle', timeout: 30000 });
  await page.waitForTimeout(1500);

  const info = await page.evaluate(() => {
    const html = document.documentElement;
    const body = document.body;
    const out = {};
    out.htmlScrollWidth = html.scrollWidth;
    out.htmlClientWidth = html.clientWidth;
    out.bodyScrollWidth = body.scrollWidth;
    out.bodyClientWidth = body.clientWidth;
    out.windowInnerWidth = window.innerWidth;
    out.htmlBackground = getComputedStyle(html).backgroundColor;
    out.bodyBackground = getComputedStyle(body).backgroundColor;
    out.horizontalOverflow = html.scrollWidth > html.clientWidth;

    // find elements wider than the viewport or with negative left offset
    const suspects = [];
    document.querySelectorAll('body *').forEach(el => {
      const r = el.getBoundingClientRect();
      if (r.width > window.innerWidth + 5 || r.left < -2 || r.right > window.innerWidth + 5) {
        suspects.push({
          tag: el.tagName,
          cls: (el.className || '').toString().slice(0, 60),
          left: Math.round(r.left), right: Math.round(r.right), width: Math.round(r.width),
        });
      }
    });
    out.suspectCount = suspects.length;
    out.suspects = suspects.slice(0, 20);
    return out;
  });

  console.log(JSON.stringify(info, null, 2));

  await page.screenshot({ path: 'products-es-mobile-top.png' });
  console.log('Saved products-es-mobile-top.png');

  // also a tight crop of the very left edge to make the stripe unmistakable
  await page.screenshot({ path: 'products-es-mobile-left-edge.png', clip: { x: 0, y: 0, width: 40, height: 800 } });
  console.log('Saved products-es-mobile-left-edge.png');

  await browser.close();
})();

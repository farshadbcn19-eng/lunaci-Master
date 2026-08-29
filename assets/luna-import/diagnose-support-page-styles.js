const { chromium } = require('playwright');

(async () => {
  const browser = await chromium.launch();
  const page = await browser.newPage({ viewport: { width: 1400, height: 1000 } });
  await page.goto('https://lunacibarcelona.com/shipping/?nocache=' + Date.now(), { waitUntil: 'load', timeout: 45000 });
  await page.waitForTimeout(1000);

  const result = await page.evaluate(() => {
    function info(sel) {
      const el = document.querySelector(sel);
      if (!el) return null;
      const cs = getComputedStyle(el);
      const r = el.getBoundingClientRect();
      return {
        backgroundColor: cs.backgroundColor,
        color: cs.color,
        fontFamily: cs.fontFamily,
        fontSize: cs.fontSize,
        maxWidth: cs.maxWidth,
        width: cs.width,
        padding: cs.padding,
        margin: cs.margin,
        rect: { width: Math.round(r.width), height: Math.round(r.height) },
      };
    }
    return {
      html: info('html'),
      body: info('body'),
      main: info('#content'),
      pageHeader: info('.page-header'),
      h1: info('h1.entry-title'),
      pageContent: info('.page-content'),
      firstParagraph: info('.page-content p'),
      link: info('.page-content a'),
    };
  });

  console.log(JSON.stringify(result, null, 2));
  await browser.close();
})();

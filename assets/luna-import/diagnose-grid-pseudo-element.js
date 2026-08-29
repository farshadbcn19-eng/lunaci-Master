const { chromium } = require('playwright');

(async () => {
  const browser = await chromium.launch();
  const page = await browser.newPage({ viewport: { width: 1400, height: 1000 } });
  await page.goto('https://lunacibarcelona.com/product-category/eyes/?nocache=' + Date.now(), { waitUntil: 'load', timeout: 45000 });
  await page.waitForTimeout(1500);

  const result = await page.evaluate(() => {
    const ul = document.querySelector('ul.products');
    if (!ul) return { error: 'ul.products not found' };

    function pseudoInfo(pseudo) {
      const cs = getComputedStyle(ul, pseudo);
      return {
        content: cs.content,
        display: cs.display,
        gridColumn: cs.gridColumn,
        gridColumnStart: cs.gridColumnStart,
        gridRow: cs.gridRow,
        width: cs.width,
        height: cs.height,
      };
    }

    return {
      ulDisplay: getComputedStyle(ul).display,
      before: pseudoInfo('::before'),
      after: pseudoInfo('::after'),
    };
  });

  console.log(JSON.stringify(result, null, 2));
  await browser.close();
})();

const { chromium } = require('playwright');

(async () => {
  const browser = await chromium.launch();
  const page = await browser.newPage({ viewport: { width: 1400, height: 1000 } });
  await page.route('**/*', route => {
    const headers = { ...route.request().headers(), 'Cache-Control': 'no-cache', 'Pragma': 'no-cache' };
    route.continue({ headers });
  });
  await page.goto('https://lunacibarcelona.com/product-category/eyes/?nocache=' + Date.now(), { waitUntil: 'networkidle', timeout: 30000 });

  const result = await page.evaluate(() => {
    const ul = document.querySelector('ul.products');
    const cs = ul ? getComputedStyle(ul) : null;

    // ALL direct children of ul.products, not just li.product - to catch any
    // hidden/empty phantom item that might be consuming a grid slot.
    const allChildren = ul ? Array.from(ul.children) : [];
    const childrenInfo = allChildren.map((el, i) => {
      const r = el.getBoundingClientRect();
      const ecs = getComputedStyle(el);
      const title = el.querySelector ? el.querySelector('.woocommerce-loop-product__title') : null;
      return {
        index: i,
        tag: el.tagName,
        class: el.className,
        title: title ? title.textContent.trim() : null,
        display: ecs.display,
        visibility: ecs.visibility,
        opacity: ecs.opacity,
        gridColumn: ecs.gridColumn,
        gridColumnStart: ecs.gridColumnStart,
        gridRow: ecs.gridRow,
        rect: { top: Math.round(r.top), left: Math.round(r.left), width: Math.round(r.width), height: Math.round(r.height) },
      };
    });

    return {
      ulFound: !!ul,
      ulClass: ul ? ul.className : null,
      ulChildElementCount: ul ? ul.childElementCount : null,
      gridTemplateColumns: cs ? cs.gridTemplateColumns : null,
      gridAutoFlow: cs ? cs.gridAutoFlow : null,
      ulRect: ul ? (function(){ const r = ul.getBoundingClientRect(); return { left: Math.round(r.left), width: Math.round(r.width) }; })() : null,
      children: childrenInfo,
    };
  });

  console.log(JSON.stringify(result, null, 2));
  await browser.close();
})();

const { chromium } = require('playwright');

(async () => {
  const browser = await chromium.launch();
  const page = await browser.newPage({ viewport: { width: 1400, height: 1000 } });
  await page.goto('https://lunacibarcelona.com/product-category/eyes/', { waitUntil: 'networkidle', timeout: 30000 });

  const result = await page.evaluate(() => {
    const cards = Array.from(document.querySelectorAll('ul.products li.product'));
    return cards.map((card, i) => {
      const rect = card.getBoundingClientRect();
      const img = card.querySelector('img');
      const title = card.querySelector('.woocommerce-loop-product__title');
      const price = card.querySelector('.price');
      const button = card.querySelector('a.button, button.button');
      const titleText = title ? title.textContent.trim() : null;

      function boxInfo(el) {
        if (!el) return null;
        const r = el.getBoundingClientRect();
        const cs = getComputedStyle(el);
        return {
          top: Math.round(r.top), left: Math.round(r.left),
          width: Math.round(r.width), height: Math.round(r.height),
          display: cs.display, visibility: cs.visibility, opacity: cs.opacity,
          zIndex: cs.zIndex, position: cs.position,
        };
      }

      return {
        index: i,
        title: titleText,
        cardRect: { top: Math.round(rect.top), left: Math.round(rect.left), width: Math.round(rect.width), height: Math.round(rect.height) },
        img: img ? {
          src: img.currentSrc || img.src,
          complete: img.complete,
          naturalWidth: img.naturalWidth,
          naturalHeight: img.naturalHeight,
          box: boxInfo(img),
        } : null,
        titleBox: boxInfo(title),
        priceBox: boxInfo(price),
        buttonBox: boxInfo(button),
      };
    });
  });

  console.log(JSON.stringify(result, null, 2));

  await browser.close();
})();

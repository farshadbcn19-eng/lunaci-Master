const { chromium } = require('playwright');

(async () => {
  const browser = await chromium.launch();

  // --- Nails banner crop check ---
  const nailsPage = await browser.newPage({ viewport: { width: 1400, height: 1000 } });
  await nailsPage.route('**/*', route => {
    const headers = { ...route.request().headers(), 'Cache-Control': 'no-cache', 'Pragma': 'no-cache' };
    route.continue({ headers });
  });
  await nailsPage.goto('https://lunacibarcelona.com/product-category/nails/?nocache=' + Date.now(), { waitUntil: 'networkidle', timeout: 30000 });

  const nailsResult = await nailsPage.evaluate(() => {
    const img = document.querySelector('.lunaci-category-banner__img');
    if (!img) return { error: 'banner img not found' };
    const cs = getComputedStyle(img);
    return {
      src: img.currentSrc || img.src,
      objectPosition: cs.objectPosition,
      objectFit: cs.objectFit,
      naturalWidth: img.naturalWidth,
      naturalHeight: img.naturalHeight,
      rect: img.getBoundingClientRect(),
    };
  });
  console.log('=== NAILS banner computed style (fresh, cache-busted) ===');
  console.log(JSON.stringify(nailsResult, null, 2));

  // --- Eyes product card re-check (hard reload, no cache) ---
  const eyesPage = await browser.newPage({ viewport: { width: 1400, height: 1000 } });
  await eyesPage.route('**/*', route => {
    const headers = { ...route.request().headers(), 'Cache-Control': 'no-cache', 'Pragma': 'no-cache' };
    route.continue({ headers });
  });
  await eyesPage.goto('https://lunacibarcelona.com/product-category/eyes/?nocache=' + Date.now(), { waitUntil: 'networkidle', timeout: 30000 });

  const cardsResult = await eyesPage.evaluate(() => {
    const cards = Array.from(document.querySelectorAll('ul.products li.product'));
    return cards.map(card => {
      const title = card.querySelector('.woocommerce-loop-product__title');
      const price = card.querySelector('.price');
      const button = card.querySelector('a.button, button.button');
      const img = card.querySelector('img');
      function vis(el) {
        if (!el) return null;
        const r = el.getBoundingClientRect();
        const cs = getComputedStyle(el);
        return { w: Math.round(r.width), h: Math.round(r.height), opacity: cs.opacity, display: cs.display };
      }
      return {
        title: title ? title.textContent.trim() : null,
        imgLoaded: img ? (img.complete && img.naturalWidth > 0) : false,
        titleVisible: vis(title),
        priceVisible: vis(price),
        buttonVisible: vis(button),
      };
    });
  });
  console.log('\n=== EYES product cards re-check (fresh, cache-busted) ===');
  console.log(JSON.stringify(cardsResult, null, 2));

  await browser.close();
})();

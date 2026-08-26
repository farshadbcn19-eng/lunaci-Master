const { chromium } = require('playwright');

(async () => {
  const browser = await chromium.launch();
  const page = await browser.newPage({ viewport: { width: 1440, height: 900 } });
  await page.goto('https://lunacibarcelona.com/shop/?nocache=' + Date.now(), { waitUntil: 'load', timeout: 45000 });
  await page.waitForTimeout(1200);

  const result = await page.evaluate(() => {
    const imgs = Array.from(document.querySelectorAll('ul.products li.product img'));
    const items = imgs.map((img) => {
      const cs = getComputedStyle(img);
      const rect = img.getBoundingClientRect();

      // find any matching CSS rules affecting filter/opacity, from all stylesheets
      const matchingRules = [];
      for (const sheet of Array.from(document.styleSheets)) {
        let rules;
        try { rules = sheet.cssRules || sheet.rules; } catch (e) { continue; }
        if (!rules) continue;
        for (const rule of Array.from(rules)) {
          if (!rule.selectorText) continue;
          try {
            if (img.matches(rule.selectorText) && /filter|opacity|blur/i.test(rule.style.cssText)) {
              matchingRules.push({ source: sheet.href || '(inline)', selector: rule.selectorText, cssText: rule.style.cssText });
            }
          } catch (e) {}
        }
      }

      return {
        src: img.currentSrc || img.src,
        alt: img.getAttribute('alt'),
        naturalWidth: img.naturalWidth,
        naturalHeight: img.naturalHeight,
        displayedWidth: rect.width,
        displayedHeight: rect.height,
        upscaleFactor: rect.width && img.naturalWidth ? +(rect.width / img.naturalWidth).toFixed(2) : null,
        computedFilter: cs.filter,
        computedOpacity: cs.opacity,
        computedBackdropFilter: cs.backdropFilter,
        computedImageRendering: cs.imageRendering,
        matchingFilterOrOpacityRules: matchingRules,
      };
    });
    return items;
  });

  console.log(JSON.stringify(result, null, 2));
  const fs = require('fs');
  fs.writeFileSync('/tmp/out/shop-images-blur.json', JSON.stringify(result, null, 2));

  await page.screenshot({ path: '/tmp/out/shop-full-desktop.png', fullPage: true });

  await browser.close();
})();

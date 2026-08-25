const { chromium } = require('playwright');

(async () => {
  const browser = await chromium.launch();
  const page = await browser.newPage({ viewport: { width: 1440, height: 900 } });
  await page.goto('https://lunacibarcelona.com/products/?nocache=' + Date.now(), { waitUntil: 'load', timeout: 45000 });
  await page.waitForTimeout(1200);

  const result = await page.evaluate(() => {
    const imgs = Array.from(document.querySelectorAll('img.prod-img'));
    let target = null;
    for (const img of imgs) {
      const alt = img.getAttribute('alt') || '';
      if (alt.toLowerCase().includes('blusher')) {
        target = img;
        break;
      }
    }
    if (!target) return { error: 'not found' };

    const matchingRules = [];
    for (const sheet of Array.from(document.styleSheets)) {
      let rules;
      try {
        rules = sheet.cssRules || sheet.rules;
      } catch (e) {
        matchingRules.push({ source: sheet.href || '(inline)', error: 'cannot read rules: ' + e.message });
        continue;
      }
      if (!rules) continue;
      for (const rule of Array.from(rules)) {
        if (!rule.selectorText) continue;
        try {
          if (target.matches(rule.selectorText)) {
            matchingRules.push({
              source: sheet.href || sheet.ownerNode?.id || '(inline)',
              selector: rule.selectorText,
              cssText: rule.style.cssText,
            });
          }
        } catch (e) {
          // invalid selector for matches(), skip
        }
      }
    }

    // Also check the img's own inline style attribute
    const inlineStyle = target.getAttribute('style');

    // Check parent chain for any inline styles too
    const parentInline = target.parentElement ? target.parentElement.getAttribute('style') : null;

    const cs = getComputedStyle(target);

    return {
      inlineStyleAttr: inlineStyle,
      parentInlineStyleAttr: parentInline,
      computedHeight: cs.height,
      computedWidth: cs.width,
      computedObjectFit: cs.objectFit,
      matchingRules,
    };
  });

  console.log(JSON.stringify(result, null, 2));
  const fs = require('fs');
  fs.writeFileSync('/tmp/out/override-info.json', JSON.stringify(result, null, 2));

  await browser.close();
})();

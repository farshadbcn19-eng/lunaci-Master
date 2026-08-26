const { chromium } = require('playwright');

(async () => {
  const browser = await chromium.launch();
  const page = await browser.newPage({ viewport: { width: 1920, height: 1000 } });
  await page.goto('https://lunacibarcelona.com/about-us/?nocache=' + Date.now(), { waitUntil: 'load', timeout: 45000 });
  await page.waitForTimeout(1200);

  const result = await page.evaluate(() => {
    const hero = document.querySelector('.lna-hero');
    if (!hero) return { error: 'not found' };

    const matchingRules = [];
    for (const sheet of Array.from(document.styleSheets)) {
      let rules;
      try { rules = sheet.cssRules || sheet.rules; } catch (e) { continue; }
      if (!rules) continue;
      for (const rule of Array.from(rules)) {
        if (!rule.selectorText) continue;
        try {
          if (hero.matches(rule.selectorText) && /width|max-width/i.test(rule.style.cssText)) {
            matchingRules.push({
              source: sheet.href || sheet.ownerNode?.id || '(inline)',
              selector: rule.selectorText,
              cssText: rule.style.cssText,
            });
          }
        } catch (e) {}
      }
    }

    // walk up the ancestor chain too, to see which ancestor actually constrains width
    const chain = [];
    let el = hero;
    let depth = 0;
    while (el && depth < 6) {
      const rect = el.getBoundingClientRect();
      const cs = getComputedStyle(el);
      chain.push({
        tag: el.tagName,
        className: el.className,
        id: el.id,
        rectWidth: rect.width,
        computedWidth: cs.width,
        computedMaxWidth: cs.maxWidth,
      });
      el = el.parentElement;
      depth++;
    }

    return {
      heroClassName: hero.className,
      heroRect: hero.getBoundingClientRect(),
      matchingRules,
      ancestorChain: chain,
    };
  });

  console.log(JSON.stringify(result, null, 2));
  const fs = require('fs');
  fs.writeFileSync('/tmp/out/lna-hero-width-source.json', JSON.stringify(result, null, 2));

  await browser.close();
})();

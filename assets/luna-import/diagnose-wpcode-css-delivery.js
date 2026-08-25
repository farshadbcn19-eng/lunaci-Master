const { chromium } = require('playwright');

(async () => {
  const browser = await chromium.launch();
  const page = await browser.newPage({ viewport: { width: 1440, height: 900 } });

  const cssResponses = [];
  page.on('response', async (res) => {
    const url = res.url();
    const ct = res.headers()['content-type'] || '';
    if (ct.includes('css') || url.includes('.css')) {
      cssResponses.push({
        url,
        status: res.status(),
        headers: res.headers(),
      });
    }
  });

  await page.goto('https://lunacibarcelona.com/products/?nocache=' + Date.now(), { waitUntil: 'load', timeout: 45000 });
  await page.waitForTimeout(1200);

  const result = await page.evaluate(() => {
    // Find which stylesheet / style tag actually contains ".prod-img" rules
    const hits = [];
    for (const sheet of Array.from(document.styleSheets)) {
      let rules;
      try {
        rules = sheet.cssRules || sheet.rules;
      } catch (e) {
        hits.push({ source: sheet.href || '(inline, cannot read - cross origin?)', error: e.message });
        continue;
      }
      if (!rules) continue;
      for (const rule of Array.from(rules)) {
        if (rule.selectorText && rule.selectorText.includes('.prod-img')) {
          hits.push({
            source: sheet.href || (sheet.ownerNode ? (sheet.ownerNode.id || sheet.ownerNode.tagName) : '(inline)'),
            selector: rule.selectorText,
            cssText: rule.style.cssText,
          });
        }
      }
    }

    // Also dump raw text of any inline <style> tags containing "prod-img"
    const inlineStyleTags = [];
    document.querySelectorAll('style').forEach((styleEl, idx) => {
      if (styleEl.textContent.includes('prod-img')) {
        const text = styleEl.textContent;
        const pos = text.indexOf('.prod-img {');
        inlineStyleTags.push({
          idx,
          id: styleEl.id || null,
          length: text.length,
          snippet: pos >= 0 ? text.substring(pos, pos + 300) : '(exact fragment not found, first 300 chars:) ' + text.substring(0, 300),
        });
      }
    });

    // Also dump link[rel=stylesheet] hrefs that might be the WPCode generated CSS
    const linkTags = Array.from(document.querySelectorAll('link[rel="stylesheet"]')).map(l => l.href);

    return { hits, inlineStyleTags, linkTags };
  });

  console.log(JSON.stringify({ result, cssResponses }, null, 2));
  const fs = require('fs');
  fs.writeFileSync('/tmp/out/wpcode-css-delivery.json', JSON.stringify({ result, cssResponses }, null, 2));

  await browser.close();
})();

const { chromium } = require('playwright');

(async () => {
  const browser = await chromium.launch();
  const page = await browser.newPage({ viewport: { width: 1920, height: 1080 } });
  await page.goto('https://lunacibarcelona.com/es/productos/?nocache=' + Date.now(), { waitUntil: 'networkidle', timeout: 60000 });

  await page.screenshot({ path: '/tmp/out/es-viewport.png' });
  await page.screenshot({ path: '/tmp/out/es-fullpage.png', fullPage: true });

  const info = await page.evaluate(() => {
    const result = {};
    result.viewport = { width: window.innerWidth, height: window.innerHeight };
    result.documentWidth = document.documentElement.scrollWidth;
    result.bodyWidth = document.body.scrollWidth;
    result.htmlOverflowX = getComputedStyle(document.documentElement).overflowX;
    result.bodyOverflowX = getComputedStyle(document.body).overflowX;

    const inner = document.querySelector('.elementor-element-0329089 > .e-con-inner, .elementor-element-0329089.e-con-inner, .elementor-element-0329089');
    const target = document.querySelector('.elementor-element-0329089');
    const innerChild = target ? target.querySelector(':scope > .e-con-inner') : null;

    if (target) {
      const r = target.getBoundingClientRect();
      const cs = getComputedStyle(target);
      result.target = {
        classList: target.className,
        rect: { x: r.x, y: r.y, width: r.width, height: r.height },
        maxWidth: cs.maxWidth, width: cs.width, marginLeft: cs.marginLeft, marginRight: cs.marginRight,
      };
    } else {
      result.target = null;
    }

    if (innerChild) {
      const r2 = innerChild.getBoundingClientRect();
      const cs2 = getComputedStyle(innerChild);
      result.innerChild = {
        classList: innerChild.className,
        rect: { x: r2.x, y: r2.y, width: r2.width, height: r2.height },
        maxWidth: cs2.maxWidth, width: cs2.width, marginLeft: cs2.marginLeft, marginRight: cs2.marginRight,
      };
    } else {
      result.innerChild = null;
    }

    // Walk ancestors of body's first meaningful child to find any width-constraining wrapper
    const ancestors = [];
    let el = target;
    let depth = 0;
    while (el && depth < 10) {
      const r = el.getBoundingClientRect();
      const cs = getComputedStyle(el);
      ancestors.push({
        tag: el.tagName, id: el.id, className: el.className,
        rect: { x: r.x, y: r.y, width: r.width, height: r.height },
        maxWidth: cs.maxWidth, width: cs.width, marginLeft: cs.marginLeft, marginRight: cs.marginRight, overflow: cs.overflow,
      });
      el = el.parentElement;
      depth++;
    }
    result.ancestors = ancestors;

    // Which stylesheets got loaded and does any contain the fix rule?
    const sheets = [];
    for (const s of document.styleSheets) {
      try {
        sheets.push({ href: s.href, ruleCount: s.cssRules ? s.cssRules.length : null });
      } catch (e) {
        sheets.push({ href: s.href, error: String(e) });
      }
    }
    result.stylesheets = sheets;

    // search all inline <style> blocks and linked CSS text content (via fetch) is not possible here synchronously,
    // but check inline styles in the DOM for the fix marker string
    const styleTags = Array.from(document.querySelectorAll('style')).map(s => s.textContent.length);
    result.inlineStyleTagLengths = styleTags;
    result.hasFixRuleInAnyInlineStyle = Array.from(document.querySelectorAll('style')).some(s => s.textContent.includes('elementor-element-0329089'));

    return result;
  });

  require('fs').writeFileSync('/tmp/out/es-info.json', JSON.stringify(info, null, 2));
  console.log(JSON.stringify(info, null, 2));

  await browser.close();
})();

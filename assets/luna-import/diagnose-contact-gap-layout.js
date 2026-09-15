const { chromium } = require('playwright');

(async () => {
  const browser = await chromium.launch();
  const page = await browser.newPage({ viewport: { width: 1400, height: 1000 } });
  await page.route('**/*', route => {
    const headers = { ...route.request().headers(), 'Cache-Control': 'no-cache', 'Pragma': 'no-cache' };
    route.continue({ headers });
  });
  await page.goto('https://lunacibarcelona.com/contact/?nocache=' + Date.now(), { waitUntil: 'networkidle', timeout: 30000 });
  await page.waitForTimeout(1000);

  const info = await page.evaluate(() => {
    function rectOf(el) {
      if (!el) return null;
      const r = el.getBoundingClientRect();
      const cs = getComputedStyle(el);
      return {
        top: r.top, bottom: r.bottom, height: r.height,
        position: cs.position, paddingTop: cs.paddingTop, marginTop: cs.marginTop,
        background: cs.backgroundImage === 'none' ? cs.backgroundColor : '(has background-image)',
        zIndex: cs.zIndex,
      };
    }
    const out = {};
    out.body = rectOf(document.body);
    out.bodyComputedPaddingTop = getComputedStyle(document.body).paddingTop;
    out.globalNav = rectOf(document.querySelector('#lunaciGlobalNav'));
    out.pageLocalHeader = rectOf(document.querySelector('header:not(#lunaciGlobalNav)'));
    out.contactHero = rectOf(document.querySelector('.contact-hero'));
    // find the element chain from body's first child down to .contact-hero to see what's between them
    const hero = document.querySelector('.contact-hero');
    const chain = [];
    let el = hero;
    while (el && el !== document.body) {
      const r = el.getBoundingClientRect();
      chain.unshift({ tag: el.tagName, id: el.id, className: (el.className || '').toString().slice(0, 80), top: r.top, height: r.height });
      el = el.parentElement;
    }
    out.ancestorChain = chain;
    return out;
  });

  console.log(JSON.stringify(info, null, 2));
  await browser.close();
})();

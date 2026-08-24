const { chromium } = require('playwright');

(async () => {
  const browser = await chromium.launch();
  const page = await browser.newPage({ viewport: { width: 1920, height: 1080 } });
  await page.goto('https://lunacibarcelona.com/products/?nocache=' + Date.now(), { waitUntil: 'networkidle', timeout: 60000 });

  await page.screenshot({ path: '/tmp/out/hero-viewport.png' });
  await page.screenshot({ path: '/tmp/out/hero-fullpage.png', fullPage: true });

  const info = await page.evaluate(() => {
    const hero = document.querySelector('.page-hero');
    const wrapper = document.querySelector('.lunaci-products-page');
    const result = {};
    if (hero) {
      const r = hero.getBoundingClientRect();
      const cs = getComputedStyle(hero);
      const beforeCs = getComputedStyle(hero, '::before');
      result.hero = {
        rect: { x: r.x, y: r.y, width: r.width, height: r.height },
        computed: {
          position: cs.position, overflow: cs.overflow, background: cs.backgroundImage,
          width: cs.width, height: cs.height, boxSizing: cs.boxSizing,
        },
        before: {
          content: beforeCs.content, position: beforeCs.position,
          top: beforeCs.top, right: beforeCs.right, bottom: beforeCs.bottom, left: beforeCs.left,
          width: beforeCs.width, height: beforeCs.height,
          backgroundImage: beforeCs.backgroundImage, backgroundSize: beforeCs.backgroundSize,
          backgroundPosition: beforeCs.backgroundPosition, backgroundRepeat: beforeCs.backgroundRepeat,
          zIndex: beforeCs.zIndex, animation: beforeCs.animation, transform: beforeCs.transform,
        },
      };
    } else {
      result.hero = null;
    }
    result.viewport = { width: window.innerWidth, height: window.innerHeight };
    result.documentWidth = document.documentElement.scrollWidth;
    result.bodyWidth = document.body.scrollWidth;

    // walk up ancestors of .page-hero to find any width-constraining container
    const ancestors = [];
    let el = hero ? hero.parentElement : null;
    let depth = 0;
    while (el && depth < 8) {
      const r = el.getBoundingClientRect();
      const cs = getComputedStyle(el);
      ancestors.push({
        tag: el.tagName, id: el.id, className: el.className,
        rect: { x: r.x, y: r.y, width: r.width, height: r.height },
        maxWidth: cs.maxWidth, width: cs.width, overflow: cs.overflow, position: cs.position,
      });
      el = el.parentElement;
      depth++;
    }
    result.ancestors = ancestors;
    return result;
  });

  require('fs').writeFileSync('/tmp/out/hero-info.json', JSON.stringify(info, null, 2));
  console.log(JSON.stringify(info, null, 2));

  await browser.close();
})();

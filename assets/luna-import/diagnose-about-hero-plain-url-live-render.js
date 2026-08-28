const { chromium } = require('playwright');

(async () => {
  const browser = await chromium.launch();
  const page = await browser.newPage({ viewport: { width: 1920, height: 1080 } });

  // Plain canonical URL, NO cache-busting query param - this is exactly what
  // the user's browser requests, so if LiteSpeed/CDN is still serving a
  // stale cached page for this literal URL (despite prior purges), this
  // will show it, whereas cache-busted fetches used in earlier verification
  // would have masked it.
  const response = await page.goto('https://lunacibarcelona.com/about-us/', {
    waitUntil: 'load',
    timeout: 45000,
  });
  await page.waitForTimeout(1500);

  const status = response.status();
  const headers = response.headers();

  const computed = await page.evaluate(() => {
    const lna = document.querySelector('#lna');
    const content = document.querySelector('.lna-hero__content');
    const heading = document.querySelector('.lna-hero__content h1, .lna-hero__content .lna-hero__title, .lna-hero__content');
    const lnaRect = lna ? lna.getBoundingClientRect() : null;
    const contentRect = content ? content.getBoundingClientRect() : null;
    const lnaStyle = lna ? getComputedStyle(lna) : null;
    const contentStyle = content ? getComputedStyle(content) : null;
    return {
      lna_found: !!lna,
      content_found: !!content,
      lna_computed_width: lnaStyle ? lnaStyle.width : null,
      lna_rect: lnaRect ? { x: lnaRect.x, width: lnaRect.width, right: lnaRect.right } : null,
      viewport_width: window.innerWidth,
      content_computed_padding: contentStyle ? contentStyle.padding : null,
      content_computed_padding_bottom: contentStyle ? contentStyle.paddingBottom : null,
      content_rect: contentRect ? { y: contentRect.y, bottom: contentRect.bottom, height: contentRect.height } : null,
      body_scroll_width: document.body.scrollWidth,
      html_scroll_width: document.documentElement.scrollWidth,
    };
  });

  const html = await page.content();

  const result = {
    url_requested: 'https://lunacibarcelona.com/about-us/ (no cache-buster, plain canonical URL)',
    status,
    x_litespeed_cache: headers['x-litespeed-cache'] || 'not set',
    cache_control: headers['cache-control'] || 'not set',
    age_header: headers['age'] || 'not set',
    contains_new_lna_fullbleed: html.includes('#lna{width:100vw'),
    contains_old_lna_width: html.includes('#lna{width:100%;background:#0B0B0B;overflow:hidden;}') && !html.includes('#lna{width:100vw'),
    contains_new_hero_media_query: html.includes('@media (min-width:1025px){.lna-hero__content{padding:0 5% 12vh'),
    count_lna_hero_content_rule_occurrences: (html.match(/\.lna-hero__content\{/g) || []).length,
    computed_styles: computed,
  };

  console.log(JSON.stringify(result, null, 2));
  const fs = require('fs');
  fs.mkdirSync('/tmp/out', { recursive: true });
  fs.writeFileSync('/tmp/out/about-hero-plain-url-live-render.json', JSON.stringify(result, null, 2));

  await page.screenshot({ path: '/tmp/out/about-hero-plain-url-1920.png', fullPage: false });

  await browser.close();
})();

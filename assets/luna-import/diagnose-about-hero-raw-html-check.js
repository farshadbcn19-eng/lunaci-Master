const { chromium } = require('playwright');

(async () => {
  const browser = await chromium.launch();
  const page = await browser.newPage();

  const response = await page.goto('https://lunacibarcelona.com/about-us/?nocache=' + Date.now(), { waitUntil: 'load', timeout: 45000 });
  const html = await response.text();

  const result = {
    status: response.status(),
    headers: response.headers(),
    htmlLength: html.length,
    contains_new_lna_width: html.includes('#lna{width:100vw'),
    contains_old_lna_width: html.includes('#lna{width:100%;background:#0B0B0B;overflow:hidden;}'),
    contains_new_hero_padding: html.includes('padding:0 5% 12vh'),
    contains_old_hero_padding: html.includes('padding:0 5% 4vh'),
    contains_overflow_x_hidden: html.includes('overflow-x:hidden'),
  };

  console.log(JSON.stringify(result, null, 2));
  const fs = require('fs');
  fs.writeFileSync('/tmp/out/about-hero-raw-html-check.json', JSON.stringify(result, null, 2));

  await browser.close();
})();

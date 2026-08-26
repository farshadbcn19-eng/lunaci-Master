const { chromium } = require('playwright');

(async () => {
  const browser = await chromium.launch();

  const viewports = {
    desktop_1440: { width: 1440, height: 900 },
    desktop_1920: { width: 1920, height: 1000 },
  };

  const results = {};

  for (const [name, viewport] of Object.entries(viewports)) {
    const page = await browser.newPage({ viewport });
    await page.goto('https://lunacibarcelona.com/about-us/?nocache=' + Date.now(), { waitUntil: 'load', timeout: 45000 });
    await page.waitForTimeout(1200);

    const info = await page.evaluate(() => {
      const bgWrap = document.querySelector('.lna-hero__bg');
      const img = bgWrap ? bgWrap.querySelector('img') : document.querySelector('.lna-hero img');
      const heroSection = document.querySelector('.lna-hero') || (bgWrap ? bgWrap.closest('section, div[class*="hero"]') : null);

      const rectOf = (el) => el ? el.getBoundingClientRect() : null;
      const styleOf = (el) => {
        if (!el) return null;
        const cs = getComputedStyle(el);
        return {
          width: cs.width, height: cs.height, objectFit: cs.objectFit, objectPosition: cs.objectPosition,
          position: cs.position, top: cs.top, left: cs.left, transform: cs.transform,
        };
      };

      // find the text overlay ("OUR STORY" or similar heading) inside the hero
      let textEl = null;
      if (heroSection) {
        textEl = heroSection.querySelector('h1, h2, .lna-hero__title, [class*="title"]');
      }

      return {
        bgWrapRect: rectOf(bgWrap),
        bgWrapStyle: styleOf(bgWrap),
        imgRect: rectOf(img),
        imgStyle: styleOf(img),
        imgNatural: img ? { naturalWidth: img.naturalWidth, naturalHeight: img.naturalHeight, src: img.currentSrc || img.src } : null,
        heroSectionRect: rectOf(heroSection),
        heroSectionClass: heroSection ? heroSection.className : null,
        textElRect: rectOf(textEl),
        textElStyle: styleOf(textEl),
        textElText: textEl ? textEl.textContent.trim().substring(0, 60) : null,
        textElClass: textEl ? textEl.className : null,
      };
    });

    results[name] = info;
    await page.screenshot({ path: `/tmp/out/about-hero-${name}.png`, fullPage: false });
    await page.close();
  }

  console.log(JSON.stringify(results, null, 2));
  const fs = require('fs');
  fs.writeFileSync('/tmp/out/about-hero-desktop-info.json', JSON.stringify(results, null, 2));

  await browser.close();
})();

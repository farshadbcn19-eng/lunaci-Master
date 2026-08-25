const { chromium } = require('playwright');
const fs = require('fs');

// The About Us widget (and likely others) uses opacity:0 scroll-reveal
// elements (.lna-rv) that only become visible via an IntersectionObserver
// adding an .on class as the user scrolls. A full-page (`fullPage: true`)
// screenshot may not perform real incremental scrolling, so it can show
// permanently-hidden content even when real user scrolling would reveal it
// correctly. This script simulates real user scrolling (small steps, with
// waits) and screenshots the actual viewport at each step, plus reports
// how many .lna-rv (or similar reveal-class) elements ended up with the
// "on"/revealed class vs how many stayed hidden - to tell a genuine bug
// apart from a screenshot-methodology artifact.

const PAGES = {
  about_en: 'https://lunacibarcelona.com/about-us/',
};

async function countRevealState(page) {
  return page.evaluate(() => {
    const all = Array.from(document.querySelectorAll('.lna-rv'));
    const revealed = all.filter((el) => el.classList.contains('on'));
    const hiddenStillZeroOpacity = all.filter((el) => {
      const cs = getComputedStyle(el);
      return parseFloat(cs.opacity) < 0.05;
    });
    return {
      totalRevealElements: all.length,
      revealedCount: revealed.length,
      stillZeroOpacityCount: hiddenStillZeroOpacity.length,
      stillZeroOpacitySamples: hiddenStillZeroOpacity.slice(0, 5).map((el) => el.className + ' :: ' + (el.textContent || '').trim().slice(0, 60)),
    };
  });
}

(async () => {
  const browser = await chromium.launch();
  const output = {};

  for (const [key, url] of Object.entries(PAGES)) {
    const page = await browser.newPage({ viewport: { width: 390, height: 844 } });
    await page.goto(url + '?nocache=' + Date.now(), { waitUntil: 'networkidle', timeout: 60000 });
    await page.waitForTimeout(500);

    const beforeScroll = await countRevealState(page);

    // Simulate real user scrolling: step down in viewport-sized increments with pauses
    const totalHeight = await page.evaluate(() => document.documentElement.scrollHeight);
    const viewportHeight = 844;
    let shots = 0;
    for (let y = 0; y < totalHeight; y += Math.floor(viewportHeight * 0.7)) {
      await page.evaluate((scrollY) => window.scrollTo(0, scrollY), y);
      await page.waitForTimeout(400);
      if (shots < 12) {
        await page.screenshot({ path: `/tmp/out/scroll-step-${String(shots).padStart(2, '0')}.png` });
        shots++;
      }
    }
    // final settle at bottom
    await page.evaluate(() => window.scrollTo(0, document.documentElement.scrollHeight));
    await page.waitForTimeout(600);

    const afterScroll = await countRevealState(page);

    output[key] = { beforeScroll, afterScroll, totalHeight };
  }

  fs.writeFileSync('/tmp/out/scroll-reveal-info.json', JSON.stringify(output, null, 2));
  console.log(JSON.stringify(output, null, 2));

  await browser.close();
})();

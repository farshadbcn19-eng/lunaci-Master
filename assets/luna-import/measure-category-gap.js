const { chromium } = require('playwright');

(async () => {
  const browser = await chromium.launch();
  const page = await browser.newPage({ viewport: { width: 390, height: 844 } });
  await page.goto('https://lunacibarcelona.com/product-category/face/', { waitUntil: 'networkidle', timeout: 30000 });

  const result = await page.evaluate(() => {
    function info(el) {
      if (!el) return null;
      const r = el.getBoundingClientRect();
      const cs = getComputedStyle(el);
      return {
        tag: el.tagName,
        id: el.id || null,
        class: el.className || null,
        top: Math.round(r.top),
        bottom: Math.round(r.bottom),
        height: Math.round(r.height),
        marginTop: cs.marginTop,
        marginBottom: cs.marginBottom,
        paddingTop: cs.paddingTop,
        paddingBottom: cs.paddingBottom,
        minHeight: cs.minHeight,
        display: cs.display,
        alignItems: cs.alignItems,
        justifyContent: cs.justifyContent,
      };
    }

    const banner = document.querySelector('.lunaci-category-banner');
    const primary = document.querySelector('#primary');
    const main = document.querySelector('#main');
    const breadcrumb = document.querySelector('.woocommerce-breadcrumb');
    const header = document.querySelector('.woocommerce-products-header');
    const h1 = document.querySelector('.woocommerce-products-header__title');
    const filterTabs = document.querySelector('.lunaci-filter-tabs');

    const chain = [];
    let node = breadcrumb;
    while (node && node !== document.documentElement) {
      chain.push(info(node));
      node = node.parentElement;
    }

    return {
      banner: info(banner),
      primary: info(primary),
      main: info(main),
      breadcrumb: info(breadcrumb),
      header: info(header),
      h1: info(h1),
      filterTabs: info(filterTabs),
      ancestorChainFromBreadcrumbToHtml: chain,
      viewportHeight: window.innerHeight,
    };
  });

  console.log(JSON.stringify(result, null, 2));
  await browser.close();
})();

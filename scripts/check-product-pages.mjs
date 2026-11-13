#!/usr/bin/env node
// Product page check for SPEC.md (consistent single-product pages), task T1.
// Read-only against the live site: it never logs in or changes data. --cart
// adds items to a throwaway browser session's cart only.
//
//   node scripts/check-product-pages.mjs --baseline        record the "before" state
//   node scripts/check-product-pages.mjs --mode=off|test|on [--cart]
//                                                           compare against the baseline
//
// test mode appends ?lunaci_perf=1 (the CSS layer is visible only there).
// Output: output/product-pages/ (gitignored). Exit code 1 when any check fails.
import { createRequire } from 'node:module';
import fs from 'node:fs';
import path from 'node:path';

// Playwright is installed globally in the session; ESM import can't resolve it.
const { chromium } = createRequire(import.meta.url)('playwright');

const BASE = 'https://lunacibarcelona.com';
const OUT = path.resolve('output/product-pages');
const GOLD = 'rgb(212, 175, 55)'; // #D4AF37: brand rule says never a fill
const args = Object.fromEntries(process.argv.slice(2).map(a => a.replace(/^--/, '').split('=')).map(([k, v]) => [k, v ?? true]));
const MODE = args.baseline ? 'baseline' : args.mode;
if (!['baseline', 'off', 'test', 'on'].includes(MODE)) {
  console.error('usage: --baseline | --mode=off|test|on [--cart]');
  process.exit(2);
}
const query = MODE === 'test' ? '?lunaci_perf=1' : '';

async function productUrls() {
  const urls = [];
  for (const lang of ['en', 'es']) {
    const url = `${BASE}/wp-json/wc/store/v1/products?per_page=50${lang === 'es' ? '&lang=es' : ''}`;
    let res;
    // Hostinger returns an occasional one-off 503; retry before giving up.
    for (let i = 0; i < 3 && !(res = await fetch(url)).ok; i++) await new Promise(r => setTimeout(r, 3000 * (i + 1)));
    if (!res.ok) throw new Error(`Store API ${lang}: HTTP ${res.status}`);
    for (const p of await res.json()) urls.push({ lang, slug: p.slug, url: p.permalink, type: p.type });
  }
  return urls;
}

// Runs in the page. Copy = what a visitor reads; tokens = computed design values.
function readPage() {
  const txt = sel => [...document.querySelectorAll(sel)].map(e => e.innerText.replace(/\s+/g, ' ').trim()).join(' | ');
  const cs = sel => { const e = document.querySelector(sel); if (!e) return null; const s = getComputedStyle(e); return { color: s.color, bg: s.backgroundColor, font: s.fontFamily.split(',')[0].replace(/["']/g, '').trim(), size: s.fontSize }; };
  const goldFills = [...document.querySelectorAll('body *')].filter(e => {
    const s = getComputedStyle(e); const r = e.getBoundingClientRect();
    return s.backgroundColor === 'rgb(212, 175, 55)' && r.width > 4 && r.height > 4 && s.visibility !== 'hidden' && s.display !== 'none';
  }).map(e => e.tagName.toLowerCase() + (e.className && typeof e.className === 'string' ? '.' + e.className.trim().split(/\s+/).join('.') : ''));
  // Page background: first ancestor-or-self with a non-transparent background.
  let bg = 'rgba(0, 0, 0, 0)';
  for (const e of [document.querySelector('main, #content, .site-main') || document.body, document.body, document.documentElement]) {
    const b = getComputedStyle(e).backgroundColor; if (b !== 'rgba(0, 0, 0, 0)') { bg = b; break; }
  }
  return {
    copy: {
      title: txt('h1.product_title, .lunaci-products-page h1'),
      price: txt('.summary p.price'),
      short: txt('.woocommerce-product-details__short-description'),
      tabs: txt('.woocommerce-tabs .panel'),
    },
    tokens: { bg, heading: cs('h1'), body: cs('.woocommerce-product-details__short-description p, main p, p') },
    markers: { layer: !!document.getElementById('lunaci-product-page') },
    goldFills,
  };
}

async function capture(browser, url, name) {
  const out = {};
  for (const [vp, size] of [['desktop', { width: 1440, height: 900 }], ['mobile', { width: 390, height: 844 }]]) {
    const page = await browser.newPage({ viewport: size });
    const res = await page.goto(url + query, { waitUntil: 'networkidle', timeout: 90000 });
    out.status = res.status();
    const data = await page.evaluate(readPage);
    if (vp === 'desktop') Object.assign(out, data);
    else out.mobileHScroll = await page.evaluate(() => document.documentElement.scrollWidth > window.innerWidth + 1);
    // Lazy-loaded images (gallery, related products) only load once scrolled
    // into view; walk the page and wait for them before the full-page shot.
    await page.evaluate(async () => {
      for (let y = 0; y < document.body.scrollHeight; y += 400) { window.scrollTo(0, y); await new Promise(r => setTimeout(r, 120)); }
      window.scrollTo(0, 0);
      await Promise.all([...document.images].filter(i => !i.complete).map(i => new Promise(r => { i.onload = i.onerror = r; setTimeout(r, 8000); })));
    });
    await page.waitForTimeout(800); // gallery fade-in
    // Full-page capture can blank the product photo on mobile (verified live: the
    // photo shows), so the above-the-fold shot is the reference for the top.
    await page.screenshot({ path: path.join(OUT, MODE, `${name}-${vp}-fold.png`) });
    await page.screenshot({ path: path.join(OUT, MODE, `${name}-${vp}.png`), fullPage: true });
    await page.close();
  }
  return out;
}

// Adds one item in a fresh session and reads the cart count from the Store API.
async function cartCheck(browser, url) {
  const ctx = await browser.newContext();
  const page = await ctx.newPage();
  try {
    await page.goto(url + query, { waitUntil: 'networkidle', timeout: 90000 });
    for (const sel of await page.$$('form.variations_form select')) {
      const value = await sel.evaluate(s => [...s.options].find(o => o.value)?.value);
      if (value) await sel.selectOption(value);
    }
    const btn = page.locator('button.single_add_to_cart_button');
    await btn.waitFor({ state: 'visible', timeout: 15000 });
    await page.waitForFunction(() => !document.querySelector('button.single_add_to_cart_button')?.classList.contains('disabled'), null, { timeout: 15000 });
    await Promise.all([page.waitForLoadState('networkidle'), btn.click()]);
    const count = await page.evaluate(async () => (await (await fetch('/wp-json/wc/store/v1/cart', { credentials: 'same-origin' })).json()).items_count);
    return { ok: count >= 1, count };
  } catch (e) {
    return { ok: false, error: e.message.split('\n')[0] };
  } finally {
    await ctx.close();
  }
}

const sameFont = (a, b) => a && b && a.font === b.font;

async function main() {
  fs.mkdirSync(path.join(OUT, MODE), { recursive: true });
  const browser = await chromium.launch();
  try {
    const products = await productUrls();
    const ref = await capture(browser, `${BASE}/products/`, 'reference-products-page');
    const pages = {};
    for (const p of products) {
      const name = `${p.lang}-${p.slug}`;
      pages[name] = { ...p, ...(await capture(browser, p.url, name)) };
      process.stdout.write('.');
    }
    console.log();
    const run = { mode: MODE, at: new Date().toISOString(), reference: { url: '/products/', tokens: ref.tokens }, pages };

    if (MODE === 'baseline') {
      const bad = Object.entries(pages).filter(([, p]) => p.status !== 200);
      fs.writeFileSync(path.join(OUT, 'baseline.json'), JSON.stringify(run, null, 2));
      console.log(`baseline: ${Object.keys(pages).length} pages (${products.filter(p => p.lang === 'en').length} EN, ${products.filter(p => p.lang === 'es').length} ES), ${bad.length} not 200`);
      console.log('reference /products/ tokens:', JSON.stringify(ref.tokens));
      process.exit(bad.length ? 1 : 0);
    }

    const base = JSON.parse(fs.readFileSync(path.join(OUT, 'baseline.json'), 'utf8')).pages;
    const fails = [];
    const diffs = [];
    for (const [name, p] of Object.entries(pages)) {
      if (p.status !== 200) fails.push(`${name}: HTTP ${p.status}`);
      if (!base[name]) { diffs.push(`${name}: not in baseline`); continue; }
      for (const k of Object.keys(p.copy)) if (p.copy[k] !== base[name].copy[k]) diffs.push(`${name}: copy changed in ${k}`);
      if (p.goldFills.length) fails.push(`${name}: gold used as fill on ${p.goldFills.slice(0, 3).join(', ')}`);
      if (p.mobileHScroll) fails.push(`${name}: horizontal scroll at 390 px`);
      if (MODE !== 'off' && !p.markers.layer) fails.push(`${name}: CSS layer marker missing in mode ${MODE}`);
      if (p.tokens.bg !== ref.tokens.bg) fails.push(`${name}: background ${p.tokens.bg} ≠ /products/ ${ref.tokens.bg}`);
      if (!sameFont(p.tokens.heading, ref.tokens.heading)) fails.push(`${name}: heading font ${p.tokens.heading?.font} ≠ /products/ ${ref.tokens.heading?.font}`);
    }
    if (args.cart) {
      // One product with shades + one without, in both languages. ES slugs are
      // explicit: the Store API lists the two languages in different orders.
      for (const [lang, slug] of [['en', 'lipstick'], ['es', 'pintalabios'], ['en', 'eye-pencil'], ['es', 'lapiz-de-ojos-lunaci']]) {
        const p = products.find(x => x.lang === lang && x.slug === slug);
        if (!p) { fails.push(`cart ${lang} ${slug}: product not found`); continue; }
        {
          const r = await cartCheck(browser, p.url);
          (r.ok ? console.log : (m => fails.push(m)))(`cart ${p.lang} ${p.slug}: ${r.ok ? 'added, count ' + r.count : 'FAILED ' + (r.error || 'count ' + r.count)}`);
        }
      }
    }
    fs.writeFileSync(path.join(OUT, `${MODE}.json`), JSON.stringify({ ...run, diffs, fails }, null, 2));
    console.log(`copy differences vs baseline: ${diffs.length}`);
    diffs.forEach(d => console.log('  DIFF ' + d));
    console.log(`failed checks: ${fails.length}`);
    fails.forEach(f => console.log('  FAIL ' + f));
    process.exit(diffs.length || fails.length ? 1 : 0);
  } finally {
    await browser.close();
  }
}

main().catch(e => { console.error(e); process.exit(2); });

# pages/

Static HTML for LUNACI pages that live in Elementor HTML widgets.

## products.html

An **exact copy** of the HTML widget on the live /products/ page:

| Field | Value |
|---|---|
| Page | ID 61 |
| Widget | `296bd28` |
| Size | 11,281 bytes |
| sha256 | `e1fe0d27142d17cc3e28c18db926de60fa6b5825332749d1773102979081e0e9` |
| Synced | 2026-11-14 |

The widget holds markup only: the `lunaci-products-page` styling is not inside it. To re-sync after the page is edited, run `query-products-page-html.yml` (read-only) and decode `BEGIN_WIDGET_0` … `END_WIDGET_0` from its log; the printed sha256 verifies the copy.

The previous `products.html` (a JavaScript catalog with a shade picker) and `products-demo.html` were not used anywhere on the live site and have been removed. They remain in git history.

# tools/

Third-party projects pulled into this repo as git submodules, used as-is
rather than written for this codebase (unlike `scripts/`, which holds small
helpers authored specifically for LUNACI).

## project-professional-portfolio

[adrianhajdin/project_professional_portfolio](https://github.com/adrianhajdin/project_professional_portfolio)
is a standalone Create React App personal-portfolio site (JS Mastery
tutorial project), with a Sanity CMS backend in its own
`backend_sanity_portfolio/` folder. It is **not related to the LUNACI brand
site** — it was added here on request, as a separate reference/starter
project. If the intent is to reuse pieces of it for LUNACI (e.g. section
layouts, animation patterns), treat it as inspiration to adapt, not code to
wire directly into the WordPress site.

### First-time clone

```bash
git submodule update --init --recursive
```

### Environment setup

```bash
cd tools/project-professional-portfolio
npm install
npm start   # dev server on http://localhost:3000
```

**Known issue:** the project pins `node-sass@7`, which fails to compile its
native addon on modern Node (tested failing on Node 22 in this environment —
it can't build against the newer V8 headers). Two ways around it:

- **Use an older Node version** (Node 16 or 18 via `nvm`) that `node-sass@7`
  still supports:
  ```bash
  nvm install 18 && nvm use 18
  npm install
  ```
- **Or swap to Dart Sass**, which has no native build step and is a drop-in
  replacement for `.scss` compilation:
  ```bash
  npm uninstall node-sass
  npm install sass
  ```
  (No import syntax changes needed for plain `.scss`/`.sass` files.)

### Sanity CMS backend

`backend_sanity_portfolio/` is a separate Sanity Studio project (its own
`package.json`). It needs its own `npm install` and a Sanity project/dataset
configured — see [Sanity's docs](https://www.sanity.io/docs) and the
upstream repo's `README.md` for details; none of that is documented here
since it wasn't set up in this environment.

### Updating the submodule

```bash
cd tools/project-professional-portfolio
git fetch origin
git checkout origin/main
cd ../..
git add tools/project-professional-portfolio
git commit -m "Bump project-professional-portfolio submodule"
```

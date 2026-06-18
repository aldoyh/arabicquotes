# Deployment

This project publishes the same cleaned static site directory to two targets.

## Static Site Build

```bash
php scripts/build-site.php _site
```

The build copies only the public site files into `_site`:

- `index.html`
- `full-quotes.html`
- `README.md`
- `LICENSE`
- `CNAME`
- `assets/`
- `docs/`

This keeps repository-only files, tests, scripts, and local tooling out of the published root.

## GitHub Pages

Workflow: `.github/workflows/deploy-pages.yml`

The workflow builds `_site` and uploads that directory through `actions/upload-pages-artifact`.

## here.now

Workflow: `.github/workflows/deploy-herenow.yml`

The workflow builds the same `_site` directory and publishes it with `.github/scripts/deploy-herenow.mjs`.

Current persistent here.now site:

- `https://lapis-waffle-fytj.here.now/`

Required repository secret:

- `HERENOW_API_KEY`: here.now API key. This is required for a persistent site.

Required repository variable:

- `HERENOW_SLUG`: existing here.now slug to update. The workflow reads this
  variable and **falls back** to `lapis-waffle-fytj` if unset. The deploy
  script refuses to run if neither the variable nor the fallback is
  available, and refuses to silently create a new site on a 404 — this is
  the safeguard that prevents a new here.now URL on every deploy.

The workflow writes the final here.now URL to the GitHub Actions step summary.

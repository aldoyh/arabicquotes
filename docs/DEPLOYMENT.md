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

- `https://blissful-hazel-gdhm.here.now/`

Required repository secret:

- `HERENOW_API_KEY`: here.now API key. This is required for a persistent site.

Optional repository variable:

- `HERENOW_SLUG`: existing here.now slug to update. Set this to `blissful-hazel-gdhm` to update the current persistent site.

The workflow writes the final here.now URL to the GitHub Actions step summary.

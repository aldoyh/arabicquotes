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

The persistent slug is tracked in `.github/herenow-site.json` (checked into
the repo), not hardcoded in the workflow. If the slug is ever deleted on
here.now's side, the script self-heals by creating a new site and committing
the newly discovered slug back to that file — see `.github/HERENOW_SETUP.md`
for the full mechanism.

Required repository secret:

- `HERENOW_API_KEY`: here.now API key. This is required for a persistent site.

Optional repository variable:

- `HERENOW_SLUG`: manual override for the persistent slug. Leave unset in
  normal operation.

The workflow writes the final here.now URL to the GitHub Actions step summary.

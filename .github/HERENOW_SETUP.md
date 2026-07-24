# here.now Persistent Deployment Setup

This guide explains how persistent here.now deployment works (same URL on
every publish, instead of a brand-new URL each time).

## How It Works

The working slug is tracked in **`.github/herenow-site.json`**, a file
checked into the repo — not a hardcoded value in the workflow. Every deploy:

1. Reads the slug from `HERENOW_SLUG` (optional manual override, set as a
   repo variable) or falls back to `.github/herenow-site.json`.
2. Sends `PUT /api/v1/publish/<slug>` to update that site in place.
3. If here.now returns 404 (the slug was deleted, or none exists yet),
   the script **self-heals**: it creates a new site with
   `POST /api/v1/publish`, then commits the newly assigned slug back into
   `.github/herenow-site.json` so every future run stays pinned to it.
4. When the slug rotates, the workflow prints a `::warning::` annotation and
   a step-summary notice — if you point a custom domain at here.now, that's
   your signal to update the DNS/CNAME target.

```
.github/herenow-site.json { "slug": "..." }
    ↓
PUT /api/v1/publish/<slug>
    ├─ 200 → done, same URL as before
    └─ 404 → POST /api/v1/publish (mints a new site)
             → write the new slug back to herenow-site.json
             → commit it, warn about the URL change
```

This replaces an earlier design that hardcoded the slug in the workflow YAML
and simply failed the run on a 404. That was safer against silently leaking
sites, but useless once the configured slug was actually gone — it just
failed forever with no path to recovery. Self-healing plus committing the
discovered slug back to the repo gives both: no silent leaks (the slug is
always visible in git history) and no permanently-broken deploys.

## Environment Variables

### Required Secrets
- `HERENOW_API_KEY` — your here.now API key.

### Optional Variables
- `HERENOW_SLUG` — manual override for the persistent slug. Leave unset in
  normal operation; the script reads `.github/herenow-site.json` instead.

## Workflow Triggers

The `deploy-herenow.yml` workflow runs on:
- Daily schedule (00:15 UTC)
- After a successful `update-quote.yml` run
- Push to `master` touching site content
- Manual trigger via GitHub Actions UI

## Cleaning Up Old Sites

To list and delete stray/ephemeral here.now deployments:

```bash
HERENOW_API_KEY=your_key node .github/scripts/cleanup-herenow.mjs list
HERENOW_API_KEY=your_key node .github/scripts/cleanup-herenow.mjs delete <slug>
```

Double-check the slug in `.github/herenow-site.json` before deleting
anything — deleting the currently-active site will trigger the self-heal
path (a new URL) on the next deploy.

## Troubleshooting

### Workflow fails with "HERENOW_API_KEY is required"
- The `HERENOW_API_KEY` repository secret is missing. Set it under
  **Settings → Secrets and variables → Actions → Secrets**.

### The site URL changed unexpectedly
- Check the latest `deploy-herenow.yml` run's step summary for a
  "slug changed" warning, and check `.github/herenow-site.json`'s git
  history — the previous slug was likely deleted on here.now. If you point
  a custom domain at the site, update it to the new slug shown there.

## References

- Deployment script: `.github/scripts/deploy-herenow.mjs`
- Workflow: `.github/workflows/deploy-herenow.yml`
- Slug state: `.github/herenow-site.json`
- here.now API docs: https://here.now/docs

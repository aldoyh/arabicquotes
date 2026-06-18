# here.now Persistent Deployment Setup

This guide explains how to configure persistent here.now deployment (same URL on every publish, instead of creating new URLs).

## Current Status

- **Deployment Script**: `.github/scripts/deploy-herenow.mjs` ✅ (ready)
- **API Key Secret**: `HERENOW_API_KEY` ✅ (required)
- **Persistent Slug**: `HERENOW_SLUG` ✅ (SET to `lapis-waffle-fytj`)

## How It Works

### Persistent Deployment (Current - Same URL Every Time)
```
HERENOW_SLUG = "lapis-waffle-fytj" (read from repo variable)
    ↓
PUT /api/v1/publish/lapis-waffle-fytj
    ↓
Result: Always "lapis-waffle-fytj.here.now"
```

### Refusal To Deploy (Safeguard)
The deploy script will now **fail the workflow run** instead of creating a
new site if either of these is true:

- `HERENOW_SLUG` is empty/missing (would have produced a brand-new random
  URL on every run).
- `PUT /api/v1/publish/<slug>` returns 404 (the persistent site has been
  deleted on here.now, or the slug was changed). The script will NOT fall
  back to `POST /api/v1/publish`, because that is exactly the behavior
  that was leaking new sites.

## Setup Instructions

### Step 1: Slug Configuration (Already Set)

The persistent slug is currently configured as:
- **Slug**: `lapis-waffle-fytj`
- **URL**: `https://lapis-waffle-fytj.here.now/`
- **Domain**: Bound to `maqeel.aldoy.net`

To change it, follow these steps:

1. Choose a new memorable slug (examples: `arabicquotes`, `aq`, `quotes-ar`)
2. Go to: **Settings → Secrets and variables → Actions → Variables**
3. Edit the `HERENOW_SLUG` variable
4. Set **Value** to your chosen slug

### Step 2: Deploy to New Slug

After updating the slug variable, trigger the workflow to deploy to the new persistent site.

### Step 3: Clean Up Old Sites (Optional)

To clean up old ephemeral deployments on here.now:

1. Go to https://here.now/
2. Log in with your account
3. Delete the old temporary sites
4. The next workflow run will deploy to your persistent slug

To list and delete sites via CLI:
```bash
HERENOW_API_KEY=your_key node .github/scripts/cleanup-herenow.mjs list
HERENOW_API_KEY=your_key node .github/scripts/cleanup-herenow.mjs delete <slug>
```

### Step 4: Verify

After setup:
1. Trigger workflow: **Actions → Deploy to here.now → Run workflow**
2. Check the deployment log
3. Verify URL is consistent: `https://lapis-waffle-fytj.here.now/`
4. Confirm subsequent deployments update the same URL

## Environment Variables

### Required Secrets
- `HERENOW_API_KEY` - Your here.now API key (must be set)

### Optional Variables
- `HERENOW_SLUG` - Persistent deployment slug (currently set to `lapis-waffle-fytj`)

## Workflow Triggers

The `deploy-herenow.yml` workflow runs on:
- Daily schedule (15:00 UTC)
- After successful `update-quote.yml` run
- Manual trigger via GitHub Actions UI

## Troubleshooting

### Workflow fails with "HERENOW_SLUG is required"
- The repo variable is unset. The script refuses to run without it because
  it would otherwise mint a brand-new here.now site on every deploy.
- Set it: **Settings → Secrets and variables → Actions → Variables →
  `HERENOW_SLUG`** = `lapis-waffle-fytj` (or whatever slug you own).

### Workflow fails with `here.now API 404: ...`
- The persistent slug has been deleted on here.now, or `HERENOW_SLUG` was
  changed to a slug that has never been claimed under this API key.
- Recover by creating the site manually (via the here.now dashboard with
  the API key), or update `HERENOW_SLUG` to a slug that already exists in
  your account. The script will no longer auto-create a new site for you.

### "Slug already exists"
- The slug may already be registered on here.now
- Choose a different slug name
- Or delete the existing deployment first

### "New URL on each deploy"
- `HERENOW_SLUG` variable is not set, **or** the slug it points to has
  been deleted on here.now. Both cases now fail loudly (see above)
  instead of producing a new URL. Restore the slug or update the variable.
- Check: Settings → Secrets and variables → Actions → Variables
- Verify the variable name is exactly `HERENOW_SLUG`

## References

- Deployment script: `.github/scripts/deploy-herenow.mjs`
- Workflow: `.github/workflows/deploy-herenow.yml`
- here.now API docs: https://here.now/docs

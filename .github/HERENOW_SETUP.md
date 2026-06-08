# here.now Persistent Deployment Setup

This guide explains how to configure persistent here.now deployment (same URL on every publish, instead of creating new URLs).

## Current Status

- **Deployment Script**: `.github/scripts/deploy-herenow.mjs` ✅ (ready)
- **API Key Secret**: `HERENOW_API_KEY` ✅ (required)
- **Persistent Slug**: `HERENOW_SLUG` ❌ (NOT SET - causing ephemeral deployments)

## How It Works

### Ephemeral Deployment (Current - Creates New URL Each Time)
```
HERENOW_SLUG = not set (empty)
    ↓
POST /api/v1/publish
    ↓
Result: Random URL like "saffron-koan-n6qp.here.now"
```

### Persistent Deployment (Target - Same URL Every Time)
```
HERENOW_SLUG = "arabicquotes" (example)
    ↓
PUT /api/v1/publish/arabicquotes
    ↓
Result: Always "arabicquotes.here.now"
```

## Setup Instructions

### Step 1: Choose a Slug
Pick a memorable slug for your site. Examples:
- `arabicquotes` → `arabicquotes.here.now`
- `aq` → `aq.here.now`
- `quotes-ar` → `quotes-ar.here.now`

### Step 2: Set Repository Variable in GitHub

1. Go to: **Settings → Secrets and variables → Actions → Variables**
2. Click **New repository variable**
3. Configure:
   - **Name**: `HERENOW_SLUG`
   - **Value**: `arabicquotes` (or your chosen slug)
4. Click **Add variable**

### Step 3: Clean Up Old Sites (Optional)

To clean up the old ephemeral deployments on here.now:

1. Go to https://here.now/
2. Log in with your account
3. Delete the old sites (keep the one you want to use)
4. The next workflow run will deploy to your persistent slug

### Step 4: Verify

After setting up:
1. Trigger workflow: **Actions → Deploy to here.now → Run workflow**
2. Check the deployment log
3. Verify URL is consistent: `https://arabicquotes.here.now/`
4. Confirm subsequent deployments update the same URL

## Environment Variables

### Required Secrets
- `HERENOW_API_KEY` - Your here.now API key (must be set)

### Optional Variables
- `HERENOW_SLUG` - Persistent deployment slug (optional, but recommended)

## Workflow Triggers

The `deploy-herenow.yml` workflow runs on:
- Daily schedule (15:00 UTC)
- After successful `update-quote.yml` run
- Manual trigger via GitHub Actions UI

## Troubleshooting

### "Slug already exists"
- The slug may already be registered on here.now
- Choose a different slug name
- Or delete the existing deployment first

### "New URL on each deploy"
- `HERENOW_SLUG` variable is not set
- Check: Settings → Secrets and variables → Actions → Variables
- Verify the variable name is exactly `HERENOW_SLUG`

### Deployment still shows as POST (ephemeral)
- Wait for next scheduled run or manually trigger
- GitHub Actions may cache the environment
- Force a re-run to pick up new variables

## References

- Deployment script: `.github/scripts/deploy-herenow.mjs`
- Workflow: `.github/workflows/deploy-herenow.yml`
- here.now API docs: https://here.now/docs

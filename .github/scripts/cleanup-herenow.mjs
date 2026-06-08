#!/usr/bin/env node

/**
 * Cleanup script to list and delete old here.now deployments
 *
 * Usage:
 *   HERENOW_API_KEY=your_key node cleanup-herenow.mjs list
 *   HERENOW_API_KEY=your_key node cleanup-herenow.mjs delete <slug>
 */

const apiBase = process.env.HERENOW_API_BASE || "https://here.now";
const apiKey = process.env.HERENOW_API_KEY;

if (!apiKey) {
  console.error("Error: HERENOW_API_KEY environment variable is required");
  process.exit(1);
}

async function request(path, options = {}) {
  const response = await fetch(`${apiBase}${path}`, {
    ...options,
    headers: {
      "Authorization": `Bearer ${apiKey}`,
      "X-HereNow-Client": "codex/cleanup-script",
      "content-type": "application/json",
      ...(options.headers || {})
    }
  });

  const text = await response.text();
  let payload;
  try {
    payload = text ? JSON.parse(text) : {};
  } catch {
    payload = { raw: text };
  }

  if (!response.ok) {
    throw new Error(`here.now API ${response.status}: ${JSON.stringify(payload)}`);
  }

  return payload;
}

async function listSites() {
  console.log("📋 Listing all your here.now deployments...\n");

  try {
    const result = await request("/api/v1/sites");
    const sites = result.sites || [];

    if (sites.length === 0) {
      console.log("No sites found.");
      return;
    }

    console.log(`Found ${sites.length} site(s):\n`);
    sites.forEach((site, i) => {
      const url = site.siteUrl || `${site.slug}.here.now`;
      console.log(`${i + 1}. ${site.slug || "Unknown"}`);
      console.log(`   URL: ${url}`);
      console.log(`   Created: ${site.createdAt || "Unknown"}`);
      console.log();
    });

    console.log("To delete a site, run:");
    console.log("  HERENOW_API_KEY=your_key node cleanup-herenow.mjs delete <slug>\n");
  } catch (error) {
    console.error("Error listing sites:", error.message);
    process.exit(1);
  }
}

async function deleteSite(slug) {
  console.log(`🗑️  Deleting site: ${slug}...\n`);

  try {
    await request(`/api/v1/sites/${encodeURIComponent(slug)}`, { method: "DELETE" });
    console.log(`✅ Successfully deleted: ${slug}`);
  } catch (error) {
    console.error(`❌ Error deleting ${slug}:`, error.message);
    process.exit(1);
  }
}

async function main() {
  const command = process.argv[2];

  if (!command || command === "list") {
    await listSites();
  } else if (command === "delete" && process.argv[3]) {
    await deleteSite(process.argv[3]);
  } else {
    console.log("here.now Cleanup Script\n");
    console.log("Usage:");
    console.log("  List all sites:");
    console.log("    HERENOW_API_KEY=your_key node cleanup-herenow.mjs list\n");
    console.log("  Delete a specific site:");
    console.log("    HERENOW_API_KEY=your_key node cleanup-herenow.mjs delete <slug>\n");
    console.log("Example:");
    console.log("    HERENOW_API_KEY=xyz123 node cleanup-herenow.mjs list");
    console.log("    HERENOW_API_KEY=xyz123 node cleanup-herenow.mjs delete saffron-koan-n6qp\n");
  }
}

main().catch(error => {
  console.error("Fatal error:", error.message);
  process.exit(1);
});

import { createHash } from "node:crypto";
import { readdir, readFile, stat } from "node:fs/promises";
import { extname, join, relative, sep } from "node:path";

const siteDir = process.argv[2] || "_site";
const apiBase = process.env.HERENOW_API_BASE || "https://here.now";
const apiKey = process.env.HERENOW_API_KEY;
const slug = process.env.HERENOW_SLUG || "";

if (!apiKey) {
  throw new Error("HERENOW_API_KEY is required for a persistent here.now deployment.");
}

const contentTypes = {
  ".css": "text/css; charset=utf-8",
  ".db": "application/vnd.sqlite3",
  ".html": "text/html; charset=utf-8",
  ".jpeg": "image/jpeg",
  ".jpg": "image/jpeg",
  ".js": "text/javascript; charset=utf-8",
  ".json": "application/json; charset=utf-8",
  ".md": "text/markdown; charset=utf-8",
  ".png": "image/png",
  ".sqlite3": "application/vnd.sqlite3",
  ".svg": "image/svg+xml",
  ".txt": "text/plain; charset=utf-8",
  ".webp": "image/webp"
};

async function walk(dir) {
  const entries = await readdir(dir, { withFileTypes: true });
  const files = [];

  for (const entry of entries) {
    if (entry.name === ".DS_Store") continue;
    const path = join(dir, entry.name);
    if (entry.isDirectory()) {
      files.push(...await walk(path));
    } else if (entry.isFile()) {
      files.push(path);
    }
  }

  return files;
}

function sitePath(file) {
  return relative(siteDir, file).split(sep).join("/");
}

async function manifestEntry(file) {
  const body = await readFile(file);
  const info = await stat(file);
  const extension = extname(file).toLowerCase();
  return {
    path: sitePath(file),
    size: info.size,
    contentType: contentTypes[extension] || "application/octet-stream",
    hash: createHash("sha256").update(body).digest("hex")
  };
}

async function request(path, options = {}) {
  const response = await fetch(`${apiBase}${path}`, {
    ...options,
    headers: {
      "Authorization": `Bearer ${apiKey}`,
      "X-HereNow-Client": "codex/github-actions",
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

const files = await walk(siteDir);
if (!files.some((file) => sitePath(file) === "index.html")) {
  throw new Error(`No index.html found in ${siteDir}`);
}

const manifest = await Promise.all(files.map(manifestEntry));
const body = {
  files: manifest,
  viewer: {
    title: "مقولات من خيرة العرب",
    description: "أرشيف عربي مفتوح للمقولات مع اختيار يومي وعداد ظهور.",
    ogImagePath: "assets/arabicquotes-header.jpg"
  }
};

let publish;
if (slug) {
  try {
    // Try to update existing persistent slug
    publish = await request(`/api/v1/publish/${encodeURIComponent(slug)}`, { method: "PUT", body: JSON.stringify(body) });
  } catch (error) {
    // If slug doesn't exist (404), create it as new persistent deployment
    if (error.message.includes("404")) {
      console.log(`Creating new persistent slug: ${slug}`);
      publish = await request(`/api/v1/publish`, { method: "POST", body: JSON.stringify({ ...body, slug: encodeURIComponent(slug) }) });
    } else {
      throw error;
    }
  }
} else {
  // Create ephemeral deployment
  publish = await request("/api/v1/publish", { method: "POST", body: JSON.stringify(body) });
}

const uploadByPath = new Map((publish.upload?.uploads || []).map((upload) => [upload.path, upload]));

await Promise.all(files.map(async (file) => {
  const upload = uploadByPath.get(sitePath(file));
  if (!upload) return;

  const data = await readFile(file);
  const response = await fetch(upload.url, {
    method: upload.method || "PUT",
    headers: upload.headers || { "Content-Type": upload.contentType || "application/octet-stream" },
    body: data
  });

  if (!response.ok) {
    throw new Error(`Upload failed for ${upload.path}: ${response.status}`);
  }
}));

const finalized = await request(
  new URL(publish.upload.finalizeUrl).pathname,
  { method: "POST", body: JSON.stringify({ versionId: publish.upload.versionId }) }
);

const siteUrl = finalized.siteUrl || publish.siteUrl;
console.log(`here.now site: ${siteUrl}`);

if (process.env.GITHUB_OUTPUT) {
  await import("node:fs/promises").then(({ appendFile }) =>
    appendFile(process.env.GITHUB_OUTPUT, `site_url=${siteUrl}\n`)
  );
}

if (process.env.GITHUB_STEP_SUMMARY) {
  await import("node:fs/promises").then(({ appendFile }) =>
    appendFile(process.env.GITHUB_STEP_SUMMARY, `### here.now deployment\n\n${siteUrl}\n\nPublished ${manifest.length} files from \`${siteDir}\`.\n`)
  );
}

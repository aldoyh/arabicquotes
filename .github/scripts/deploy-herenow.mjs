import { createHash } from "node:crypto";
import { readdir, readFile, stat, writeFile } from "node:fs/promises";
import { extname, join, relative, sep } from "node:path";

const siteDir = process.argv[2] || "_site";
const apiBase = process.env.HERENOW_API_BASE || "https://here.now";
const apiKey = process.env.HERENOW_API_KEY;

// The persistent slug's source of truth is this checked-in file, not a
// hardcoded value in the workflow YAML. That way, if here.now ever forces us
// to rotate to a new slug (see recovery logic below), the discovered slug is
// committed back to the repo and every future run stays pinned to it.
const stateFile = new URL("../herenow-site.json", import.meta.url);

async function readState() {
  try {
    return JSON.parse(await readFile(stateFile, "utf8"));
  } catch {
    return {};
  }
}

if (!apiKey) {
  throw new Error("HERENOW_API_KEY is required for a persistent here.now deployment.");
}

const state = await readState();
// HERENOW_SLUG env var (repo variable) is an explicit manual override; absent
// that, trust the last slug we know actually exists on here.now.
const slug = process.env.HERENOW_SLUG || state.slug || "";

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

function slugFromUrl(url) {
  if (!url) return "";
  try {
    return new URL(url).hostname.split(".")[0];
  } catch {
    return "";
  }
}

let publish;
let rotated = false;

if (slug) {
  try {
    // Update the known-good persistent site.
    publish = await request(
      `/api/v1/publish/${encodeURIComponent(slug)}`,
      { method: "PUT", body: JSON.stringify(body) }
    );
  } catch (error) {
    if (!error.message.includes("404")) throw error;
    // The slug we had on file no longer exists on here.now (deleted, or the
    // account/API key changed). Recover by minting a new site instead of
    // failing the workflow forever — the discovered slug gets persisted
    // below so every future run stays pinned to it again.
    console.warn(`here.now slug "${slug}" no longer exists (404). Creating a new persistent site.`);
    rotated = true;
  }
}

if (!publish) {
  publish = await request("/api/v1/publish", { method: "POST", body: JSON.stringify(body) });
  rotated = true;
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
const actualSlug = slugFromUrl(siteUrl) || slug;
console.log(`here.now site: ${siteUrl}`);

if (rotated && actualSlug) {
  await writeFile(stateFile, JSON.stringify({ slug: actualSlug }, null, 2) + "\n");
  console.warn(`Persisted new here.now slug "${actualSlug}" to .github/herenow-site.json.`);
}

if (process.env.GITHUB_OUTPUT) {
  const { appendFile } = await import("node:fs/promises");
  await appendFile(process.env.GITHUB_OUTPUT, `site_url=${siteUrl}\n`);
  await appendFile(process.env.GITHUB_OUTPUT, `slug_rotated=${rotated}\n`);
}

if (process.env.GITHUB_STEP_SUMMARY) {
  const { appendFile } = await import("node:fs/promises");
  const rotationNotice = rotated
    ? `\n> ⚠️ **The persistent slug changed to \`${actualSlug}\`.** If you point a custom domain at here.now, update that DNS/CNAME target now.\n`
    : "";
  await appendFile(
    process.env.GITHUB_STEP_SUMMARY,
    `### here.now deployment\n\n${siteUrl}\n${rotationNotice}\nPublished ${manifest.length} files from \`${siteDir}\`.\n`
  );
}

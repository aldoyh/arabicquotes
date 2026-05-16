<?php

$root = dirname(__DIR__);
$output = $argv[1] ?? ($root . '/_site');
if (!str_starts_with($output, '/')) {
    $output = $root . '/' . $output;
}

function removeDirectory($dir)
{
    if (!is_dir($dir)) {
        return;
    }

    $items = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );

    foreach ($items as $item) {
        $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
    }
    rmdir($dir);
}

function copyFileToSite($root, $output, $relativePath)
{
    $source = $root . '/' . $relativePath;
    $target = $output . '/' . $relativePath;
    if (!file_exists($source)) {
        return;
    }
    if (!is_dir(dirname($target))) {
        mkdir(dirname($target), 0775, true);
    }
    copy($source, $target);
}

function copyDirectoryToSite($root, $output, $relativePath, $allowedExtensions = null)
{
    $sourceDir = $root . '/' . $relativePath;
    if (!is_dir($sourceDir)) {
        return;
    }

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($sourceDir, FilesystemIterator::SKIP_DOTS)
    );

    foreach ($iterator as $file) {
        if (!$file->isFile()) {
            continue;
        }
        $extension = strtolower(pathinfo($file->getFilename(), PATHINFO_EXTENSION));
        if ($allowedExtensions !== null && !in_array($extension, $allowedExtensions, true)) {
            continue;
        }
        $relative = substr($file->getPathname(), strlen($root) + 1);
        copyFileToSite($root, $output, $relative);
    }
}

removeDirectory($output);
mkdir($output, 0775, true);

$rootFiles = [
    'index.html',
    'full-quotes.html',
    'README.md',
    'LICENSE',
    'CNAME',
    'favicon.svg',
];

foreach ($rootFiles as $file) {
    copyFileToSite($root, $output, $file);
}

copyDirectoryToSite($root, $output, 'assets', ['css', 'db', 'html', 'jpg', 'jpeg', 'json', 'png', 'sqlite3', 'svg', 'webp']);
copyDirectoryToSite($root, $output, 'docs', ['html', 'md', 'css', 'jpg', 'jpeg', 'png', 'svg', 'webp']);

$marker = [
    'builtAt' => gmdate('c'),
    'source' => 'scripts/build-site.php',
    'files' => iterator_count(new RecursiveIteratorIterator(new RecursiveDirectoryIterator($output, FilesystemIterator::SKIP_DOTS))),
];

file_put_contents(
    $output . '/site-manifest.json',
    json_encode($marker, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) . PHP_EOL
);

echo "Built static site at {$output}\n";

#!/usr/bin/env node

/**
 * 构建后预压缩脚本
 *
 * 扫描指定目录下的 JS/CSS 文件，生成 .gz 和 .br 预压缩副本。
 * Web 服务器（Apache mod_rewrite / Nginx gzip_static）可直接提供这些文件，
 * 避免实时压缩的 CPU 开销，同时获得更高压缩率。
 *
 * 用法: node scripts/compress.js
 */

const fs = require("fs");
const path = require("path");
const zlib = require("zlib");

const TARGETS = [
    "public/admin/assets",
    "public/themes/default/assets",
];

const EXTENSIONS = [".js", ".css"];

// 仅压缩大于 1KB 的文件，小文件压缩收益低
const MIN_SIZE = 1024;

function formatSize(bytes) {
    if (bytes < 1024) return bytes + " B";
    if (bytes < 1024 * 1024) return (bytes / 1024).toFixed(1) + " KB";
    return (bytes / (1024 * 1024)).toFixed(2) + " MB";
}

function compressFile(filePath) {
    const raw = fs.readFileSync(filePath);
    if (raw.length < MIN_SIZE) return;

    const basename = path.basename(filePath);
    const results = [];

    // gzip (level 9 for max compression)
    const gzipped = zlib.gzipSync(raw, { level: 9 });
    const gzPath = filePath + ".gz";
    fs.writeFileSync(gzPath, gzipped);
    results.push({ format: "gz", size: gzipped.length });

    // brotli (quality 11 for max compression, static content)
    const brotlied = zlib.brotliCompressSync(raw, {
        params: {
            [zlib.constants.BROTLI_PARAM_QUALITY]: 11,
        },
    });
    const brPath = filePath + ".br";
    fs.writeFileSync(brPath, brotlied);
    results.push({ format: "br", size: brotlied.length });

    const ratio = ((1 - brotlied.length / raw.length) * 100).toFixed(1);
    console.log(
        `  ${basename}: ${formatSize(raw.length)} → gz ${formatSize(gzipped.length)} / br ${formatSize(brotlied.length)} (${ratio}% saved)`
    );
}

function processDirectory(dir) {
    const root = path.resolve(dir);
    if (!fs.existsSync(root)) {
        console.log(`  skip: ${dir} (not found)`);
        return;
    }

    const files = fs.readdirSync(root);
    for (const file of files) {
        const ext = path.extname(file).toLowerCase();
        if (!EXTENSIONS.includes(ext)) continue;

        // 跳过源文件（-src.js）
        if (file.endsWith("-src.js")) continue;

        compressFile(path.join(root, file));
    }
}

console.log("Pre-compressing static assets...\n");
for (const dir of TARGETS) {
    console.log(`[${dir}]`);
    processDirectory(dir);
    console.log();
}
console.log("Done.");

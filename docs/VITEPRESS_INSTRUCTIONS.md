# VitePress Development Instructions

## Local Development

The VitePress site is configured with `base: '/bob-the-builder/'` for GitHub Pages deployment.

### Start Dev Server

```bash
cd docs
npx vitepress dev
```

Then open: **http://localhost:5173/bob-the-builder/**

Important: Note the `/bob-the-builder/` path - this is required for local development!

### If Sidebar Doesn't Show

1. **Clear cache and rebuild:**
```bash
cd docs
rm -rf .vitepress/cache .vitepress/dist node_modules/.vite
npx vitepress build
npx vitepress dev
```

2. **Hard refresh in browser:**
   - Chrome/Firefox: Ctrl+Shift+R (Linux/Windows) or Cmd+Shift+R (Mac)
   - Or open DevTools (F12) and right-click refresh button → "Empty Cache and Hard Reload"

3. **Check the correct URL:**
   - ✅ Correct: `http://localhost:5173/bob-the-builder/`
   - ❌ Wrong: `http://localhost:5173/` (will show blank or broken)

### Alternative: Local Development Without Base Path

If you want to develop without the `/bob-the-builder/` path:

1. Create `docs/.vitepress/config.local.mjs`:
```javascript
import { defineConfig } from 'vitepress'
import baseConfig from './config.mjs'

export default defineConfig({
  ...baseConfig,
  base: '/', // Override for local dev
})
```

2. Run with local config:
```bash
npx vitepress dev --config .vitepress/config.local.mjs
```

## Building for Production

```bash
cd docs
npx vitepress build
```

Built files will be in `docs/.vitepress/dist/`

## Deploying to GitHub Pages

The site is configured for GitHub Pages at: https://marwen-brini.github.io/bob-the-builder/

To deploy:
```bash
cd docs
npx vitepress build
# Then push the dist folder or use GitHub Actions
```

## Troubleshooting

### "Page not found" or blank pages
- Check you're using the full URL with `/bob-the-builder/` path
- Clear browser cache (Ctrl+Shift+R)
- Clear VitePress cache: `rm -rf .vitepress/cache`

### Sidebar not showing
- Check config.mjs has proper sidebar configuration
- Verify guide files exist in `docs/guide/`
- Clear cache and rebuild
- Check browser console for errors (F12)

### Changes not reflecting
- Stop dev server (Ctrl+C)
- Clear cache: `rm -rf .vitepress/cache`
- Restart: `npx vitepress dev`

## Current Site Structure

```
docs/
├── .vitepress/
│   ├── config.mjs          # Main configuration
│   ├── cache/              # Build cache (can delete)
│   └── dist/               # Built site (can delete)
├── guide/
│   ├── getting-started.md
│   ├── migrations.md       # NEW in v3.0
│   ├── schema-builder.md   # NEW in v3.0
│   ├── wordpress-schema.md # NEW in v3.0
│   ├── schema-inspector.md # NEW in v3.0
│   └── ...
├── api/
│   └── index.md
└── index.md                # Homepage
```

## Navigation Structure

The sidebar is configured in `config.mjs` with these sections:

1. **Getting Started**
   - What's New (v3.0.0)
   - Introduction
   - Installation
   - Configuration
   - Quick Start

2. **Core Features**
   - Query Builder
   - Where Clauses
   - Joins
   - Models

3. **Schema & Migrations (v3.0)** ← NEW SECTION
   - Database Migrations
   - Schema Builder
   - WordPress Schema
   - Schema Inspector

4. **Advanced**
   - Performance
   - Logging
   - Extending Bob
   - CLI Tools
   - Migration Guide
   - Troubleshooting
   - Changelog

All navigation should work once you access the correct URL with the base path!

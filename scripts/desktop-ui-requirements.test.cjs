const fs = require('fs');
const assert = require('assert');

const html = fs.readFileSync('public/index.html', 'utf8');
const css = fs.readFileSync('public/assets/app.css', 'utf8');
const js = fs.readFileSync('public/assets/app.js', 'utf8');

assert.match(html, /class="desktop-sidebar"/, 'desktop app shell needs a primary sidebar');
assert.match(html, /class="desktop-manage-nav"/, 'management needs desktop subnavigation');
assert.match(html, /class="desktop-editor-slot"/, 'management needs a persistent desktop editor slot');
assert.match(html, /id="desktopExerciseEditorSlot"/, 'exercise management needs a desktop editor slot');
assert.match(html, /id="desktopWorkoutEditorSlot"/, 'workout management needs a desktop editor slot');
assert.match(html, /id="desktopGymEditorSlot"/, 'gym management needs a desktop editor slot');

assert.match(css, /@media \(min-width: 1024px\)/, 'desktop layout must start at 1024px');
assert.match(css, /\.desktop-sidebar/, 'desktop sidebar must be styled');
assert.match(css, /\.bottom-nav\s*\{[\s\S]*?display:\s*none/, 'bottom nav must be hidden on desktop');
assert.match(css, /\.screen\s*\{[\s\S]*?max-width:\s*none/, 'desktop screens must not keep the narrow mobile max width');
assert.match(css, /\.manage-desktop-grid/, 'desktop management must use a grid layout');
assert.match(css, /\.desktop-editor-slot/, 'desktop management editor slot must be styled');
assert.match(css, /\.history-desktop-layout/, 'desktop history must have a wider analysis layout');

assert.match(js, /const DESKTOP_QUERY = '\(min-width: 1024px\)'/, 'JS needs an explicit desktop breakpoint');
assert.match(js, /function isDesktopLayout\(\)/, 'JS needs a desktop layout helper');
assert.match(js, /getDefaultTab\(\)/, 'app must pick a desktop-specific default tab');
assert.match(js, /switchTab\(getDefaultTab\(\)\)/, 'bootstrap must switch to desktop default tab');
assert.match(js, /openManageSection\(getDefaultManageSection\(\)\)/, 'management must default to exercises on desktop');
assert.match(js, /desktopEditorSlotId/, 'management forms must support desktop editor slots');
assert.match(js, /document\.querySelectorAll\('\.app-nav button'\)/, 'all app nav instances must stay in sync');

const fs = require('fs');
const assert = require('assert');

const html = fs.readFileSync('public/index.html', 'utf8');
const css = fs.readFileSync('public/assets/app.css', 'utf8');
const spritePath = 'public/assets/icons/sprite.svg';
const sprite = fs.existsSync(spritePath) ? fs.readFileSync(spritePath, 'utf8') : '';

assert.match(css, /--color-graphite:\s*#111827/);
assert.match(css, /--color-plate-blue:\s*#2453E6/);
assert.match(css, /--color-tape-orange:\s*#F26A2E/);
assert.match(css, /font-family:\s*"IBM Plex Sans"/);
assert.match(css, /font-family:\s*"Barlow Condensed"/);
assert.match(html, /assets\/fonts\/IBMPlexSans-Regular\.woff2/);
assert.match(sprite, /id="icon-train"/);
assert.match(html, /aria-current="page"/, 'navigation should expose its current page to assistive technology');
assert.match(html, /aria-live="polite"/, 'global feedback should be announced');
assert.match(html, /<dialog[^>]+aria-labelledby="confirmDialogTitle"[^>]+aria-describedby="confirmDialogMessage"/, 'the reusable confirmation dialog should have an accessible name and description');
assert.match(css, /:focus-visible[\s\S]*outline:\s*3px/, 'keyboard focus should remain visible');
assert.match(css, /@media\s*\(prefers-reduced-motion:\s*reduce\)/, 'the interface should respect reduced-motion preferences');
assert.match(css, /button\s*\{[\s\S]*?min-height:\s*44px/, 'primary controls should meet the 44px touch target');
assert.match(css, /input,\s*select,\s*textarea\s*\{[\s\S]*?min-height:\s*44px/, 'form controls should meet the 44px touch target');
assert.doesNotMatch(css, /min-height:\s*(?:3[0-9]|4[0-3])px/, 'compact controls must not shrink below the 44px touch target');
assert.doesNotMatch(fs.readFileSync('public/assets/app.js', 'utf8'), /\b(?:window\.)?confirm\s*\(/, 'destructive actions should use the shared accessible dialog');

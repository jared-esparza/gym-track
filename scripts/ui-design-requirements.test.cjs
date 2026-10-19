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

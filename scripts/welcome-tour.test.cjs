const fs = require('node:fs');
const vm = require('node:vm');
const assert = require('node:assert/strict');

const html = fs.readFileSync('public/index.html', 'utf8');
assert.ok(html.includes('id="welcomeView"'), 'root needs a public product welcome');
assert.ok(!html.includes('auth-specimen'), 'remove the fake account record');
assert.ok(html.includes('id="demoTourDialog"'), 'demo needs an accessible tour');
for (const query of ['?login=1', '?register=1', '#como-funciona']) assert.ok(html.includes(query));

const elements = new Map();
function el(id) {
  if (!elements.has(id)) {
    const classes = new Set();
    elements.set(id, { id, open: false, hidden: false, textContent: '', style: {}, disabled: false,
      classList: { add: x => classes.add(x), remove: x => classes.delete(x), contains: x => classes.has(x), toggle(x, force) { if (force) classes.add(x); else classes.delete(x); } },
      showModal() { this.open = true; }, close() { this.open = false; }, focus() {}, scrollIntoView() {},
      getBoundingClientRect: () => ({ left: 20, top: 100, width: 300, height: 100, bottom: 200, right: 320 }),
      addEventListener() {}, setAttribute() {}, querySelector: () => null,
      querySelectorAll: () => [], getClientRects: () => [1], isConnected: true });
  }
  return elements.get(id);
}
let saved = null;
const events = [];
const context = {
  document: { getElementById: el, activeElement: el('launch'), querySelector: () => null },
  window: { addEventListener() {}, removeEventListener() {}, matchMedia: () => ({ matches: false }), innerWidth: 1280, innerHeight: 900 },
  localStorage: { getItem: () => saved, setItem: (_, value) => { saved = value; } },
  requestAnimationFrame: callback => { callback(); return 1; }, cancelAnimationFrame() {}, console,
};
vm.runInNewContext(fs.readFileSync('public/assets/demo-tour.js', 'utf8'), context);
const Tour = context.window.DemoTour;
assert.equal(typeof Tour, 'function');
const tour = new Tour({ isDemo: () => true, prepare: async step => { events.push(step); return el('target'); }, finish: async () => events.push('finish'), report: () => {} });
(async () => {
  await tour.start();
  assert.equal(el('demoTourDialog').open, true);
  assert.match(el('demoTourProgress').textContent, /1 de 6/);
  let offscreenTop = 1800;
  el('target').getBoundingClientRect = () => ({ left: 20, top: offscreenTop, width: 300, height: 100, bottom: offscreenTop + 100, right: 320 });
  context.window.scrollBy = ({ top }) => { offscreenTop -= top; };
  tour.onResize();
  assert.ok(offscreenTop < 900, 'resizing must reveal the target, not leave its highlight offscreen');
  await tour.next(); await tour.back();
  assert.match(el('demoTourProgress').textContent, /1 de 6/);
  await tour.close(true);
  assert.equal(saved, 'dismissed');
  await tour.start();
  assert.equal(el('demoTourDialog').open, false, 'skip must persist');
  await tour.start(true);
  for (let i = 0; i < 6; i++) await tour.next();
  assert.equal(saved, 'completed');
  assert.equal(el('demoTourDialog').open, false);
  context.localStorage.getItem = () => { throw new Error('blocked'); };
  context.localStorage.setItem = () => { throw new Error('blocked'); };
  const blocked = new Tour({ isDemo: () => true, prepare: async () => null, finish: async () => {}, report() {} });
  await blocked.start(); await blocked.close(true); await blocked.start();
  assert.equal(el('demoTourDialog').open, false, 'blocked storage needs an in-memory fallback');
  let sessionSeen = null;
  context.localStorage.getItem = () => null;
  context.sessionStorage = { getItem: () => sessionSeen, setItem: (_, value) => { sessionSeen = value; } };
  const quota = new Tour({ isDemo: () => true, prepare: async () => null, finish: async () => {} });
  await quota.start(); await quota.close(true);
  const afterReload = new Tour({ isDemo: () => true, prepare: async () => null, finish: async () => {} });
  await afterReload.start();
  assert.equal(el('demoTourDialog').open, false, 'readable but full localStorage must still consult the session fallback');
  const real = new Tour({ isDemo: () => false, prepare: async () => { throw new Error('real user tour'); }, finish() {} });
  await real.start(true);
  assert.equal(el('demoTourDialog').open, false);
  assert.equal(events.filter(x => x === 'finish').length, 2);
  console.log('Welcome and tour: steps, back/next, skip, completion, replay, blocked storage and real-account exclusion passed.');
})().catch(error => { console.error(error); process.exitCode = 1; });

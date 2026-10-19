const fs = require('fs');
const vm = require('vm');
const assert = require('assert');

class FakeClassList {
  constructor(names = []) { this.names = new Set(names); }
  add(name) { this.names.add(name); }
  remove(name) { this.names.delete(name); }
  contains(name) { return this.names.has(name); }
  toggle(name, force) {
    const enabled = force === undefined ? !this.contains(name) : Boolean(force);
    if (enabled) this.add(name); else this.remove(name);
    return enabled;
  }
}

function element({ dataset = {}, classes = [] } = {}) {
  return {
    dataset: { ...dataset },
    classList: new FakeClassList(classes),
    attributes: {},
    listeners: {},
    textContent: '',
    setAttribute(name, value) { this.attributes[name] = value; },
    removeAttribute(name) { delete this.attributes[name]; },
    addEventListener(name, listener) { this.listeners[name] = listener; },
    focus() { document.activeElement = this; this.focused = true; },
  };
}

const navButtons = [
  element({ dataset: { tab: 'trainTab' }, classes: ['active'] }),
  element({ dataset: { tab: 'historyTab' } }),
  element({ dataset: { tab: 'manageTab' } }),
  element({ dataset: { tab: 'trainTab' }, classes: ['active'] }),
  element({ dataset: { tab: 'historyTab' } }),
  element({ dataset: { tab: 'manageTab' } }),
];
const opener = element();
const globalMessage = element({ classes: ['hidden'] });
const dialog = element();
dialog.showModal = function showModal() { this.open = true; };
dialog.close = function close() { this.open = false; };
const dialogTitle = element();
const dialogMessage = element();
const dialogConfirm = element();
const dialogCancel = element();

const elements = new Map([
  ['globalMessage', globalMessage],
  ['confirmDialog', dialog],
  ['confirmDialogTitle', dialogTitle],
  ['confirmDialogMessage', dialogMessage],
  ['confirmDialogConfirm', dialogConfirm],
  ['confirmDialogCancel', dialogCancel],
]);

const document = {
  activeElement: opener,
  getElementById(id) { return elements.get(id) || element(); },
  querySelectorAll(selector) {
    if (selector === '.app-nav button') return navButtons;
    if (selector === '.screen') return [];
    return [];
  },
};

let source = fs.readFileSync('public/assets/app.js', 'utf8');
source = source.replace(/init\(\)\.catch\([\s\S]*?\n\}\);\s*$/, '');
source += `\n;globalThis.__shellTest = {
  setActiveNavigation: typeof setActiveNavigation === 'function' ? setActiveNavigation : null,
  announce: typeof announce === 'function' ? announce : null,
  openConfirmDialog: typeof openConfirmDialog === 'function' ? openConfirmDialog : null,
};`;

const context = {
  console,
  document,
  window: { matchMedia: () => ({ matches: false, addEventListener() {} }) },
  URLSearchParams,
  FormData,
};
context.globalThis = context;
vm.runInNewContext(source, context, { filename: 'public/assets/app.js' });

const api = context.__shellTest;
assert.equal(typeof api.setActiveNavigation, 'function', 'shell must expose navigation synchronization');
assert.equal(typeof api.announce, 'function', 'shell must expose persistent live feedback');
assert.equal(typeof api.openConfirmDialog, 'function', 'shell must expose the reusable confirmation dialog');

api.setActiveNavigation('historyTab');
navButtons.forEach((button) => {
  const selected = button.dataset.tab === 'historyTab';
  assert.equal(button.classList.contains('active'), selected, 'both navigation instances must stay visually synchronized');
  assert.equal(button.attributes['aria-current'], selected ? 'page' : undefined, 'only the active destination may expose aria-current');
});

api.announce('Marca guardada', 'success');
assert.equal(globalMessage.textContent, 'Marca guardada');
assert.equal(globalMessage.attributes['aria-live'], 'polite');
assert.equal(globalMessage.attributes['data-tone'], 'success');
assert.equal(globalMessage.classList.contains('hidden'), false);

(async () => {
  document.activeElement = opener;
  const resultPromise = api.openConfirmDialog({
    title: 'Eliminar registro',
    message: 'Esta acción no se puede deshacer.',
    confirmLabel: 'Eliminar',
  });
  assert.equal(dialog.open, true, 'dialog must open modally');
  assert.equal(dialogConfirm.focused, true, 'dialog must move focus to its primary action');
  let prevented = false;
  dialog.listeners.cancel({ preventDefault() { prevented = true; } });
  assert.equal(await resultPromise, false, 'Escape must resolve confirmation as false');
  assert.equal(prevented, true, 'native cancel must be handled to centralize cleanup');
  assert.equal(opener.focused, true, 'dialog must restore focus to its opener');
})().catch((error) => {
  console.error(error);
  process.exitCode = 1;
});

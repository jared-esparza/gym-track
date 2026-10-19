const fs = require('fs');
const vm = require('vm');
const assert = require('assert');

const html = fs.readFileSync('public/index.html', 'utf8');
const css = fs.readFileSync('public/assets/app.css', 'utf8');
let source = fs.readFileSync('public/assets/app.js', 'utf8');
source = source.replace(/init\(\)\.catch\([\s\S]*?\n\}\);\s*$/, '');
source += `\n;globalThis.__authTest = {
  state,
  showAuthMode: typeof showAuthMode === 'function' ? showAuthMode : null,
  loadOnboardingProgress: typeof loadOnboardingProgress === 'function' ? loadOnboardingProgress : null,
  renderOnboardingState: typeof renderOnboardingState === 'function' ? renderOnboardingState : null,
};`;

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
function element(classes = []) {
  return {
    classList: new FakeClassList(classes), dataset: {}, open: false, textContent: '', innerHTML: '',
    attributes: {}, listeners: {},
    addEventListener(name, listener) { this.listeners[name] = listener; },
    setAttribute(name, value) { this.attributes[name] = value; },
  };
}
const ids = [
  'loginForm', 'registerDetails', 'forgotDetails', 'resetForm', 'onboarding', 'onboardingSteps',
  'onboardingActionBtn', 'onboardingWorkoutBtn',
];
const elements = new Map(ids.map((id) => [id, element(['hidden'])]));
const document = {
  getElementById(id) { if (!elements.has(id)) elements.set(id, element()); return elements.get(id); },
  querySelectorAll() { return []; },
};
const context = { console, document, window: { matchMedia: () => ({ matches: false }) }, URLSearchParams, FormData };
context.globalThis = context;
vm.runInNewContext(source, context, { filename: 'public/assets/app.js' });
const api = context.__authTest;

assert.equal(typeof api.loadOnboardingProgress, 'function', 'onboarding should read progress from existing exercise data');
assert.equal(typeof api.renderOnboardingState, 'function', 'onboarding should render the next useful step');
api.showAuthMode('register');
assert.equal(elements.get('loginForm').classList.contains('hidden'), true);
assert.equal(elements.get('registerDetails').classList.contains('hidden'), false);
assert.equal(elements.get('registerDetails').open, true);
assert.equal(elements.get('forgotDetails').classList.contains('hidden'), true);
assert.equal(elements.get('resetForm').classList.contains('hidden'), true);
api.showAuthMode('reset');
assert.equal(elements.get('loginForm').classList.contains('hidden'), true);
assert.equal(elements.get('registerDetails').classList.contains('hidden'), true);
assert.equal(elements.get('forgotDetails').classList.contains('hidden'), true);
assert.equal(elements.get('resetForm').classList.contains('hidden'), false);

api.renderOnboardingState({ workouts: 0, exercises: 0, records: 0 });
for (const text of ['Crea tu primer entrenamiento', 'Añade tu primer ejercicio', 'Registra tu primera marca']) {
  assert(elements.get('onboardingSteps').innerHTML.includes(text), `onboarding should explain: ${text}`);
}
assert.match(elements.get('onboardingActionBtn').textContent, /Crear entrenamiento/);
api.renderOnboardingState({ workouts: 1, exercises: 0, records: 0 });
assert.match(elements.get('onboardingActionBtn').textContent, /Añadir ejercicio/);
api.renderOnboardingState({ workouts: 1, exercises: 2, records: 0 });
assert.match(elements.get('onboardingActionBtn').textContent, /Registrar marca/);
api.renderOnboardingState({ workouts: 1, exercises: 2, records: 1 });
assert.equal(elements.get('onboarding').classList.contains('hidden'), true, 'completed onboarding should disappear');

context.api = async () => ({ exercises: [
  { id: 1, record_count: '2' }, { id: 2, record_count: 3 }, { id: 3, record_count: '0' },
] });
api.loadOnboardingProgress().then((progress) => {
  assert.deepEqual(JSON.parse(JSON.stringify(progress)), { workouts: 0, exercises: 3, records: 5 });
  assert.match(html, /class="auth-brand"/);
  assert.match(html, /Press banca/i);
  assert.match(css, /\.onboarding-steps/);
}).catch((error) => {
  console.error(error);
  process.exitCode = 1;
});

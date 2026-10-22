const fs = require('node:fs');
const vm = require('node:vm');
const assert = require('node:assert/strict');
const source = fs.readFileSync('public/assets/app.js', 'utf8').replace(/init\(\)\.catch\([\s\S]*?\n\}\);\s*$/, '') + '\n;globalThis.subject = { init, state, selectTrainingExercise };';
function harness(search, user = null) {
  const nodes = new Map();
  function get(id) {
    if (!nodes.has(id)) {
      const classes = new Set(['hidden']);
      nodes.set(id, { reset() {}, classList: { add: x => classes.add(x), remove: x => classes.delete(x), contains: x => classes.has(x), toggle: (x, force) => force ? classes.add(x) : classes.delete(x) }, elements: { token: { value: '' }, notes: { value: '' } } });
    }
    return nodes.get(id);
  }
  const events = [];
  const context = { URLSearchParams, console, location: { search }, window: { matchMedia: () => ({ matches: false }) }, document: { getElementById: get } };
  vm.runInNewContext(source, context);
  context.bindAuth = () => {}; context.bindApp = () => {};
  context.api = async () => ({ user, csrf_token: 'test' });
  context.showMessage = message => events.push(message);
  context.startDemo = async () => events.push('demo');
  context.showApp = async () => events.push('app');
  return { context, get, events };
}
(async () => {
  for (const query of ['', '?login=1', '?register=1', '?reset=token', '?verified=1', '?demo=1']) {
    const { context, get, events } = harness(query);
    await context.subject.init();
    const account = /login|register|reset|verified/.test(query);
    assert.equal(get('authView').classList.contains('hidden'), !account, query);
    assert.equal(get('welcomeView').classList.contains('hidden'), account, query);
    assert.equal(events.includes('demo'), query.includes('demo'));
    if (query.includes('reset')) assert.equal(get('resetForm').elements.token.value, 'token');
    if (query.includes('register')) assert.equal(get('registerDetails').open, true);
    if (query.includes('verified')) assert.match(events[0], /verificado/);
  }
  const loggedIn = harness('', { id: 42 });
  await loggedIn.context.subject.init();
  assert.deepEqual(loggedIn.events, ['app']);
  const slow = harness('');
  let resolveSummary, cancelled = false;
  slow.context.api = () => new Promise(resolve => { resolveSummary = resolve; });
  slow.context.renderTrainingExerciseList = () => {};
  const pending = slow.context.subject.selectTrainingExercise(3, () => cancelled);
  cancelled = true;
  slow.get('trainExerciseSelect').value = '';
  resolveSummary({ exercise: { id: 3, name: 'Old tour example', metric_type: 'kg', notes: '' }, rm: null, last_record: null });
  await pending;
  assert.equal(slow.get('exercisePanel').classList.contains('hidden'), true, 'cancelled tour summary must not reopen its panel');
  assert.equal(slow.context.subject.state.activeExercise, null, 'late summary must not restore cancelled tour selection');
  console.log('Welcome routes: root, login, registration, reset, verification, direct demo and active session passed.');
})().catch(error => { console.error(error); process.exitCode = 1; });

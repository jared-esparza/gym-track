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

function element({ value = '', classes = [] } = {}) {
  return {
    value,
    textContent: '',
    innerHTML: '',
    className: '',
    dataset: {},
    attributes: {},
    classList: new FakeClassList(classes),
    elements: { id: { value: '' }, value: { value: '' }, note: { value: '' } },
    options: [],
    listeners: {},
    setAttribute(name, nextValue) { this.attributes[name] = nextValue; },
    removeAttribute(name) { delete this.attributes[name]; },
    addEventListener(name, listener) { this.listeners[name] = listener; },
    querySelectorAll() { return []; },
    querySelector() { return null; },
    reset() { this.resetCalled = true; },
    scrollIntoView() {},
  };
}

const ids = [
  'historyPanel', 'historyGate', 'historyGateMessage', 'historySummary', 'historyExerciseName',
  'historyGymContext', 'historyBestValue', 'historyLatestValue', 'historyDeltaValue',
  'historyRecordCount', 'historyChart', 'historyEmptyState', 'historyCards', 'historyRows',
  'recordEditForm', 'historyExerciseSelect', 'historyGymSelect', 'historyGroupSelect',
  'historyChartFrame',
];
const elements = new Map(ids.map((id) => [id, element({ classes: ['hidden'] })]));
elements.get('historyChart').classList.add('hidden');
elements.get('historyExerciseSelect').value = '';

const document = {
  getElementById(id) {
    if (!elements.has(id)) elements.set(id, element());
    return elements.get(id);
  },
  querySelectorAll() { return []; },
};

let source = fs.readFileSync('public/assets/app.js', 'utf8');
source = source.replace(/init\(\)\.catch\([\s\S]*?\n\}\);\s*$/, '');
source += `\n;globalThis.__historyTest = {
  state,
  summarizeHistory: typeof summarizeHistory === 'function' ? summarizeHistory : null,
  renderHistorySummary: typeof renderHistorySummary === 'function' ? renderHistorySummary : null,
  historyChartOptions: typeof historyChartOptions === 'function' ? historyChartOptions : null,
  renderHistory: typeof renderHistory === 'function' ? renderHistory : null,
  loadHistory: typeof loadHistory === 'function' ? loadHistory : null,
};`;

const context = {
  console,
  document,
  window: { matchMedia: () => ({ matches: false, addEventListener() {} }) },
  URLSearchParams,
  FormData,
  fetch: async () => ({ async json() { return { ok: true, records: [], chart: [] }; } }),
};
context.globalThis = context;
vm.runInNewContext(source, context, { filename: 'public/assets/app.js' });

const api = context.__historyTest;
assert.equal(typeof api.summarizeHistory, 'function', 'history must expose a pure progress summary');
assert.equal(typeof api.renderHistorySummary, 'function', 'history must render the summary values');
assert.equal(typeof api.historyChartOptions, 'function', 'chart scaling must be independently testable');

assert.deepEqual(JSON.parse(JSON.stringify(api.summarizeHistory([]))), {
  best: null, latest: null, delta: null, count: 0, metricType: null,
});

const single = [{ value: '80', metric_type: 'kg', recorded_at: '2026-10-18 10:00:00' }];
assert.deepEqual(JSON.parse(JSON.stringify(api.summarizeHistory(single))), {
  best: 80, latest: 80, delta: null, count: 1, metricType: 'kg',
});

const unorderedProgress = [
  { value: '105', metric_type: 'kg', recorded_at: '2026-10-19 10:00:00' },
  { value: '80', metric_type: 'kg', recorded_at: '2026-10-01 10:00:00' },
  { value: '95', metric_type: 'kg', recorded_at: '2026-10-10 10:00:00' },
];
assert.deepEqual(JSON.parse(JSON.stringify(api.summarizeHistory(unorderedProgress))), {
  best: 105, latest: 105, delta: 25, count: 3, metricType: 'kg',
});

const unchangedScale = api.historyChartOptions([80, 80]);
assert.equal(unchangedScale.scales.y.beginAtZero, true, 'flat or single-value series should retain a zero baseline');
const progressScale = api.historyChartOptions([80, 95, 105]);
assert.equal(progressScale.scales.y.beginAtZero, false, 'visible progress may use a padded, legible scale');
assert.equal(progressScale.scales.y.min, 75);
assert.equal(progressScale.scales.y.max, 110);

api.state.gymsEnabled = false;
api.renderHistory({ records: [], chart: [] });
assert.equal(elements.get('historySummary').classList.contains('hidden'), false);
assert.equal(elements.get('historyRecordCount').textContent, '0');
assert.match(elements.get('historyEmptyState').innerHTML, /data-action="train"/);

api.renderHistory({ records: unorderedProgress, chart: [] });
assert.equal(elements.get('historyBestValue').textContent, '105 kg');
assert.equal(elements.get('historyLatestValue').textContent, '105 kg');
assert.equal(elements.get('historyDeltaValue').textContent, '+25 kg');

context.Chart = undefined;
api.renderHistory({ records: single, chart: [{ day: '2026-10-18', value: 80 }] });
assert.match(elements.get('historyEmptyState').textContent, /El gráfico no está disponible/);
assert.equal(elements.get('historySummary').classList.contains('hidden'), false, 'summary remains useful when charts are unavailable');

(async () => {
  api.state.gymsEnabled = true;
  elements.get('historyGymSelect').value = '';
  elements.get('historyExerciseSelect').value = '1';
  await api.loadHistory();
  assert.equal(elements.get('historySummary').classList.contains('hidden'), true);
  assert.equal(elements.get('historyChartFrame').classList.contains('hidden'), true);
  assert.equal(elements.get('historyGateMessage').textContent, 'Elige gimnasio primero para consultar el progreso.');
  assert.equal(elements.get('historyGate').classList.contains('hidden'), false);
})().catch((error) => {
  console.error(error);
  process.exitCode = 1;
});

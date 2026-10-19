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
    innerHTML: '',
    textContent: '',
    dataset: {},
    attributes: {},
    options: [],
    classList: new FakeClassList(classes),
    elements: { notes: { value: '' } },
    reset() { this.resetCalled = true; },
    setAttribute(name, nextValue) { this.attributes[name] = nextValue; },
    removeAttribute(name) { delete this.attributes[name]; },
    querySelectorAll() { return []; },
  };
}

const elements = new Map();
[
  'trainGymSelect', 'trainExerciseSelect', 'trainingExerciseList', 'trainingExerciseName',
  'recordUnit', 'rmValue', 'lastValue', 'exerciseNotes', 'exerciseNotesForm', 'recordForm',
  'exercisePanel', 'globalMessage',
].forEach((id) => elements.set(id, element({ classes: id === 'exercisePanel' ? ['hidden'] : [] })));

const document = {
  activeElement: null,
  getElementById(id) {
    if (!elements.has(id)) elements.set(id, element());
    return elements.get(id);
  },
  querySelectorAll() { return []; },
  createElement() { return element(); },
};

const pressBanca = { id: 1, name: 'Press banca', group_name: 'Pectoral', metric_type: 'kg' };
const curlBiceps = { id: 2, name: 'Curl bíceps', group_name: 'Bíceps', metric_type: 'kg' };

let source = fs.readFileSync('public/assets/app.js', 'utf8');
source = source.replace(/init\(\)\.catch\([\s\S]*?\n\}\);\s*$/, '');
source += `\n;globalThis.__trainingTest = {
  state,
  filterTrainingExercises: typeof filterTrainingExercises === 'function' ? filterTrainingExercises : null,
  renderTrainingExerciseList: typeof renderTrainingExerciseList === 'function' ? renderTrainingExerciseList : null,
  selectTrainingExercise: typeof selectTrainingExercise === 'function' ? selectTrainingExercise : null,
  renderRecordConfirmation: typeof renderRecordConfirmation === 'function' ? renderRecordConfirmation : null,
};`;

const context = {
  console,
  document,
  window: { matchMedia: () => ({ matches: false, addEventListener() {} }) },
  URLSearchParams,
  FormData,
  fetch: async () => ({
    async json() {
      return {
        ok: true,
        exercise: { ...pressBanca, notes: 'Controla la pausa.' },
        rm: 100,
        last_record: { value: 95, metric_type: 'kg', recorded_at: '2026-10-18 10:00:00' },
      };
    },
  }),
};
context.globalThis = context;
vm.runInNewContext(source, context, { filename: 'public/assets/app.js' });

const api = context.__trainingTest;
assert.equal(typeof api.filterTrainingExercises, 'function', 'training flow must expose normalized filtering');
assert.equal(typeof api.renderTrainingExerciseList, 'function', 'training flow must render the enhanced exercise list');
assert.equal(typeof api.selectTrainingExercise, 'function', 'training flow must synchronize enhanced and native controls');
assert.equal(typeof api.renderRecordConfirmation, 'function', 'training flow must provide contextual save feedback');

api.state.groups = [{ id: 10, name: 'Pectoral' }, { id: 20, name: 'Bíceps' }];
api.state.trainExercises = [pressBanca, curlBiceps];
assert.deepEqual(api.filterTrainingExercises('press'), [pressBanca]);
assert.deepEqual(api.filterTrainingExercises('PECTORAL'), [pressBanca]);

api.state.gymsEnabled = true;
elements.get('trainGymSelect').value = '';
api.renderTrainingExerciseList(api.state.trainExercises);
assert.match(elements.get('trainingExerciseList').innerHTML, /Elige gimnasio primero/);

(async () => {
  api.state.gymsEnabled = false;
  await api.selectTrainingExercise(1);
  assert.equal(elements.get('trainExerciseSelect').value, '1');
  assert.equal(elements.get('trainingExerciseName').textContent, 'Press banca');
  assert.equal(elements.get('recordUnit').textContent, '(kg)');
  assert.equal(elements.get('rmValue').textContent, '100 kg');
  assert.match(elements.get('lastValue').textContent, /95 kg/);

  api.renderRecordConfirmation({ value: '101', metricType: 'kg', previousRm: 100 });
  assert.equal(elements.get('globalMessage').textContent, 'Nueva RM: 101 kg');

  const css = fs.readFileSync('public/assets/app.css', 'utf8');
  assert.match(css, /\.exercise-option__name[\s\S]*overflow-wrap:\s*anywhere/);
})().catch((error) => {
  console.error(error);
  process.exitCode = 1;
});

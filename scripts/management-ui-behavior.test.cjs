const fs = require('fs');
const vm = require('vm');
const assert = require('assert');

const html = fs.readFileSync('public/index.html', 'utf8');
const css = fs.readFileSync('public/assets/app.css', 'utf8');
let source = fs.readFileSync('public/assets/app.js', 'utf8');
source = source.replace(/init\(\)\.catch\([\s\S]*?\n\}\);\s*$/, '');
source += `\n;globalThis.__managementTest = {
  state,
  entityMeta: typeof entityMeta === 'function' ? entityMeta : null,
  setManagementEditorState: typeof setManagementEditorState === 'function' ? setManagementEditorState : null,
  deleteExercise, deleteWorkout, deleteGym,
};`;

const emptySlots = ['exercises', 'workouts', 'gyms', 'data'].map((section) => ({
  dataset: { editorEmpty: section },
  classList: { hidden: true, toggle(name, hidden) { this.hidden = Boolean(hidden); } },
}));
const slot = { dataset: {} };
const elements = { desktopEditorEmpty: emptySlots };
const document = {
  querySelector(selector) { return selector === '.desktop-editor-slot' ? slot : null; },
  querySelectorAll(selector) { return selector === '[data-editor-empty]' ? emptySlots : []; },
  getElementById() { return null; },
};
const context = { console, document, window: { matchMedia: () => ({ matches: true }) }, URLSearchParams, FormData };
context.globalThis = context;
vm.runInNewContext(source, context, { filename: 'public/assets/app.js' });
const api = context.__managementTest;

assert.equal(typeof api.entityMeta, 'function', 'management metadata should use one semantic formatter');
assert.equal(typeof api.setManagementEditorState, 'function', 'management should expose explicit editor states');
assert.equal(api.entityMeta(['Pecho', null, 'kg', '2 registros']), '<span class="entity-meta__part">Pecho</span><span class="entity-meta__part">kg</span><span class="entity-meta__part">2 registros</span>');
assert.match(api.entityMeta(['<script>', 'Press']), /&lt;script&gt;/, 'metadata must escape user-controlled text');

api.setManagementEditorState('workouts', 'empty');
assert.equal(slot.dataset.activeSection, 'workouts');
assert.equal(slot.dataset.editorMode, 'empty');
assert.equal(emptySlots[1].classList.hidden, false, 'the selected section should show contextual empty-editor guidance');
assert.equal(emptySlots[0].classList.hidden, true, 'other sections should not show empty-editor guidance');
api.setManagementEditorState('workouts', 'edit');
assert.equal(emptySlots[1].classList.hidden, true, 'empty guidance should hide while editing');

const confirmationCalls = [];
const announcements = [];
const apiCalls = [];
context.openConfirmDialog = async (options) => { confirmationCalls.push(options); return true; };
context.api = async (path) => { apiCalls.push(path); return {}; };
context.announce = (message, tone) => announcements.push({ message, tone });
context.loadBootstrap = async () => {};
context.hideManageExerciseForm = () => {};
context.clearDeletedExerciseSelections = () => {};
context.loadManageExercises = async () => {};
context.loadActiveWorkout = async () => {};
context.loadExercises = async () => {};
context.refreshWorkoutExerciseChoices = async () => {};
context.refreshOnboarding = async () => {};
api.deleteWorkout(4);
api.deleteGym(5);
api.state.manageExercises = [{ id: 6, name: 'Press banca', record_count: 3 }];
api.deleteExercise(6);

setImmediate(() => {
  assert.deepEqual(confirmationCalls.map((call) => call.title), ['Eliminar entrenamiento', 'Eliminar gimnasio', 'Eliminar ejercicio']);
  assert(confirmationCalls[2].message.includes('3'), 'exercise deletion must preserve the associated-record warning');
  assert(apiCalls.some((path) => path.startsWith('workout&id=4')));
  assert(apiCalls.some((path) => path.startsWith('gym&id=5')));
  assert(apiCalls.some((path) => path.startsWith('exercise&id=6')));
  assert.equal(announcements.length, 3, 'successful deletions should announce completion');
  assert(announcements.every((announcement) => announcement.tone === 'success'));
  assert.match(html, /data-editor-empty="exercises"/);
  assert.match(html, /data-editor-empty="workouts"/);
  assert.match(html, /data-editor-empty="gyms"/);
  assert.match(css, /entity-meta__part[\s\S]*overflow-wrap:\s*anywhere/);
});

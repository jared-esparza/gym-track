const fs = require('fs');
const vm = require('vm');
const assert = require('assert');

function element() {
  const classes = new Set();
  return {
    value: '', dataset: {}, innerHTML: '', textContent: '', listeners: {}, options: [],
    elements: { id: { value: '' }, name: { value: '', focus() {} }, muscle_group_id: { value: '' } },
    classList: {
      add(name) { classes.add(name); },
      remove(name) { classes.delete(name); },
      contains(name) { return classes.has(name); },
      toggle(name, force) { if (force) classes.add(name); else classes.delete(name); },
    },
    addEventListener(name, listener) { (this.listeners[name] ||= []).push(listener); },
    click() { for (const listener of this.listeners.click || []) listener(); this.onclick?.(); },
    querySelectorAll() { return []; },
    querySelector() { return null; },
    appendChild(child) { child.parent = this; },
    setAttribute() {}, reset() {}, focus() {},
  };
}

function harness() {
  const elements = new Map();
  const get = (id) => { if (!elements.has(id)) elements.set(id, element()); return elements.get(id); };
  const slot = element();
  const context = {
    console, URLSearchParams, FormData,
    window: { matchMedia: () => ({ matches: true, addEventListener() {} }) },
    document: {
      getElementById: get,
      querySelector: (selector) => selector === '.desktop-editor-slot' ? slot : null,
      querySelectorAll: () => [],
    },
  };
  let source = fs.readFileSync('public/assets/app.js', 'utf8').replace(/init\(\)\.catch\([\s\S]*?\n\}\);\s*$/, '');
  source += '\n;globalThis.testApi = { state, bindApp, renderOnboardingState, renderHistory, hideManagementForm, createExercise, saveRecord, saveManagedExercise, deleteExercise, deleteRecord };';
  vm.runInNewContext(source, context);
  return { context, api: context.testApi, get, slot };
}

(async () => {
  {
    const { context, api, get } = harness();
    context.bindManageAccordion = () => {};
    context.bindResponsiveLayout = () => {};
    context.switchTab = () => {};
    context.openManageSection = () => {};
    let opened = 0;
    get('quickExerciseForm').elements.name.focus = () => { opened++; };
    api.bindApp();
    api.renderOnboardingState({ workouts: 0, exercises: 0, records: 0 });
    get('onboardingActionBtn').click();
    assert.equal(opened, 1, 'one onboarding click must open its editor exactly once');
  }
  {
    const { context, api, get } = harness();
    const action = element();
    get('historyEmptyState').querySelector = () => action;
    let destination;
    context.switchTab = (tabId) => { destination = tabId; };
    api.renderHistory({ records: [], chart: [] });
    action.click();
    assert.equal(destination, 'trainTab', 'empty history should navigate to the actual training tab');
  }
  {
    const { api, slot } = harness();
    api.state.manageOpenSection = 'workouts';
    slot.dataset.activeSection = 'workouts';
    slot.dataset.editorMode = 'edit';
    api.hideManagementForm({ formId: 'exerciseManagementForm', homeId: 'exerciseFormHome', listId: 'manageExerciseList' });
    assert.equal(slot.dataset.activeSection, 'workouts', 'refreshing another list must preserve the active management editor');
    assert.equal(slot.dataset.editorMode, 'edit');
  }
  for (const operation of ['createExercise', 'saveRecord', 'saveManagedExercise', 'deleteExercise', 'deleteRecord']) {
    const { context, api, get } = harness();
    let progressReads = 0;
    const errors = [];
    context.api = async () => ({});
    context.send = async () => ({ id: 1 });
    context.formData = () => ({ value: '80', name: 'Press' });
    context.showMessage = (message, tone) => { if (tone === 'error') errors.push(message); };
    context.announce = () => {};
    context.openConfirmDialog = async () => true;
    const refreshedContexts = [];
    context.loadExercises = async (where) => { refreshedContexts.push(where); };
    context.loadActiveWorkout = async () => {};
    context.refreshWorkoutExerciseChoices = async () => {};
    context.selectTrainingExercise = async () => {};
    context.loadExerciseSummary = async () => {};
    context.loadManageExercises = async () => {};
    context.refreshExerciseSelectorsAfterManagedSave = async () => {};
    context.loadHistory = async () => {};
    context.hideManageExerciseForm = () => {};
    context.clearDeletedExerciseSelections = () => {};
    context.focusManagementSection = () => {};
    context.loadOnboardingProgress = async () => { progressReads++; return { workouts: 1, exercises: 1, records: 1 }; };
    context.renderOnboardingState = () => {};
    api.state.manageExercises = [{ id: 1, name: 'Press', record_count: 1 }];
    api.state.activeExercise = { metric_type: 'kg' };
    get('trainGroupSelect').value = '1';
    get('manageExerciseGroupSelect').value = '2';
    const form = element();
    form.elements.metric_type = { value: 'kg' };
    if (operation.startsWith('delete')) await api[operation](1);
    else await api[operation]({ preventDefault() {}, currentTarget: form });
    assert.deepEqual(errors, [], `${operation} should finish its save/delete flow`);
    if (operation === 'createExercise') assert(refreshedContexts.includes('history'), 'new exercise must be immediately available in history');
    assert.equal(progressReads, 1, `${operation} must refresh onboarding independently of the management group filter`);
  }
})().catch((error) => { console.error(error); process.exitCode = 1; });

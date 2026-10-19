const fs = require('fs');
const vm = require('vm');
const assert = require('assert');

class FakeClassList {
  constructor(names = []) {
    this.names = new Set(names);
  }

  add(name) {
    this.names.add(name);
  }

  remove(name) {
    this.names.delete(name);
  }

  contains(name) {
    return this.names.has(name);
  }

  toggle(name, force) {
    const enabled = force === undefined ? !this.contains(name) : Boolean(force);
    if (enabled) this.add(name);
    else this.remove(name);
    return enabled;
  }
}

function element({ classes = [], dataset = {}, idValue = '' } = {}) {
  return {
    classList: new FakeClassList(classes),
    dataset: { ...dataset },
    elements: { id: { value: idValue } },
    parent: null,
    toggle: null,
    items: [],
    appendChild(child) {
      child.parent = this;
    },
    querySelector(selector) {
      if (selector === '[data-manage-toggle]') return this.toggle;
      return null;
    },
    querySelectorAll(selector) {
      if (selector === '.item[data-entity-id]') return this.items;
      if (selector === '.is-editing') return this.items.filter((item) => item.classList.contains('is-editing'));
      return [];
    },
    setAttribute(name, value) {
      this[name] = value;
    },
  };
}

const mediaQuery = {
  matches: true,
  listener: null,
  addEventListener(event, listener) {
    if (event === 'change') this.listener = listener;
  },
};

const elements = new Map();
const manageTab = element({ classes: ['active'] });
const editorSlot = element();
const exerciseSection = element({ dataset: { manageSection: 'exercises', open: 'false' } });
exerciseSection.toggle = element();
const exerciseNav = element({ dataset: { manageToggle: 'exercises' } });
const exerciseForm = element({ idValue: '17' });
const desktopExerciseSlot = element();
const exerciseList = element();
const exerciseItem = element({ dataset: { entityId: '17' } });
exerciseList.items.push(exerciseItem);
exerciseItem.appendChild(exerciseForm);

elements.set('manageTab', manageTab);
elements.set('exerciseManagementForm', exerciseForm);
elements.set('newExerciseFormSlot', element());
elements.set('desktopExerciseEditorSlot', desktopExerciseSlot);
elements.set('manageExerciseList', exerciseList);
elements.set('workoutForm', element({ classes: ['hidden'] }));
elements.set('newWorkoutFormSlot', element());
elements.set('desktopWorkoutEditorSlot', element());
elements.set('workoutList', element());
elements.set('gymForm', element({ classes: ['hidden'] }));
elements.set('newGymFormSlot', element());
elements.set('desktopGymEditorSlot', element());
elements.set('gymList', element());

const document = {
  getElementById(id) {
    return elements.get(id) || null;
  },
  querySelector(selector) {
    if (selector === '.desktop-editor-slot') return editorSlot;
    return null;
  },
  querySelectorAll(selector) {
    if (selector === '[data-manage-section]') return [exerciseSection];
    if (selector === '.manage-nav-button') return [exerciseNav];
    return [];
  },
};

let source = fs.readFileSync('public/assets/app.js', 'utf8');
source = source.replace(/init\(\)\.catch\([\s\S]*?\n\}\);\s*$/, '');
source += '\n;globalThis.__layoutTest = { getDefaultTab, bindResponsiveLayout, state };';

const context = {
  console,
  document,
  window: { matchMedia: () => mediaQuery },
  URLSearchParams,
  FormData,
};
context.globalThis = context;
vm.runInNewContext(source, context, { filename: 'public/assets/app.js' });

const api = context.__layoutTest;

assert.equal(api.getDefaultTab(), 'trainTab', 'desktop must preserve Entrenar as the primary destination');

api.state.manageOpenSection = null;
api.bindResponsiveLayout();
assert.equal(typeof mediaQuery.listener, 'function', 'responsive layout must listen for breakpoint changes');

mediaQuery.listener({ matches: true });

assert.equal(api.state.manageOpenSection, 'exercises', 'desktop management must open Exercises after crossing the breakpoint');
assert.equal(exerciseSection.dataset.open, 'true', 'the active management section must be visible on desktop');
assert.equal(exerciseForm.parent, desktopExerciseSlot, 'a visible edit form must move into the desktop editor slot');

mediaQuery.matches = false;
mediaQuery.listener({ matches: false });

assert.equal(exerciseForm.parent, exerciseItem, 'the visible edit form must return to its item on mobile');


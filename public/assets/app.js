const state = {
  user: null,
  groups: [],
  workouts: [],
  activeWorkout: null,
  activeWorkoutGroups: [],
  activeExercise: null,
  manageExercises: [],
  importPreviewToken: null,
  csrfToken: '',
  chart: null,
};

const $ = (id) => document.getElementById(id);

async function api(action, options = {}) {
  const method = (options.method || 'GET').toUpperCase();
  const headers = { ...(options.headers || {}) };
  if (!(options.body instanceof FormData)) {
    headers['Content-Type'] = 'application/json';
  }
  if (['POST', 'PUT', 'PATCH', 'DELETE'].includes(method) && state.csrfToken) {
    headers['X-CSRF-Token'] = state.csrfToken;
  }
  const response = await fetch(`api.php?action=${action}`, {
    credentials: 'same-origin',
    ...options,
    headers,
  });
  const data = await response.json();
  if (!data.ok) throw new Error(data.error || 'Error inesperado');
  return data;
}

async function send(action, payload = {}, method = 'POST') {
  return api(action, { method, body: JSON.stringify(payload) });
}

function showMessage(text, type = 'ok', target = $('globalMessage')) {
  target.textContent = text;
  target.classList.toggle('error', type === 'error');
  target.classList.remove('hidden');
}

function clearMessage(target = $('globalMessage')) {
  target.classList.add('hidden');
}

function formatValue(value, metric) {
  if (value === null || value === undefined || value === '') return '-';
  const number = Number(value);
  const clean = Number.isInteger(number) ? String(number) : number.toFixed(2).replace(/0+$/, '').replace(/\.$/, '');
  return `${clean} ${metric}`;
}

function fillSelect(select, items, placeholder = 'Selecciona') {
  select.innerHTML = `<option value="">${placeholder}</option>`;
  items.forEach((item) => {
    const option = document.createElement('option');
    option.value = item.id;
    option.textContent = item.name;
    select.appendChild(option);
  });
}

function fillExerciseSelect(select, exercises, placeholder, showGroup = false) {
  select.innerHTML = `<option value="">${placeholder}</option>`;
  exercises.forEach((exercise) => {
    const option = document.createElement('option');
    option.value = exercise.id;
    option.textContent = formatExerciseName(exercise, showGroup);
    select.appendChild(option);
  });
}

function formatExerciseName(exercise, showGroup = false) {
  return showGroup ? `${groupName(exercise.muscle_group_id)} · ${exercise.name}` : exercise.name;
}

function formData(form) {
  return Object.fromEntries(new FormData(form).entries());
}

async function init() {
  bindAuth();
  bindApp();
  const params = new URLSearchParams(location.search);
  if (params.get('verified')) showMessage('Email verificado. Ya puedes iniciar sesión.', 'ok', $('authMessage'));
  if (params.get('reset')) {
    $('resetForm').classList.remove('hidden');
    $('resetForm').elements.token.value = params.get('reset');
  }

  const me = await api('me');
  state.csrfToken = me.csrf_token || '';
  if (me.user) {
    state.user = me.user;
    await showApp();
  } else {
    showAuth();
  }
}

function showAuth() {
  $('authView').classList.remove('hidden');
  $('appView').classList.add('hidden');
}

async function showApp() {
  $('authView').classList.add('hidden');
  $('appView').classList.remove('hidden');
  $('userEmail').textContent = state.user.email;
  await loadBootstrap();
}

function bindAuth() {
  $('loginForm').addEventListener('submit', async (event) => {
    event.preventDefault();
    const form = event.currentTarget;
    try {
      clearMessage($('authMessage'));
      await send('login', formData(form));
      const me = await api('me');
      state.csrfToken = me.csrf_token || state.csrfToken;
      state.user = me.user;
      await showApp();
    } catch (error) {
      showMessage(error.message, 'error', $('authMessage'));
    }
  });

  $('registerForm').addEventListener('submit', async (event) => {
    event.preventDefault();
    const form = event.currentTarget;
    try {
      const data = await send('register', formData(form));
      showMessage(data.message, 'ok', $('authMessage'));
      form.reset();
    } catch (error) {
      showMessage(error.message, 'error', $('authMessage'));
    }
  });

  $('forgotForm').addEventListener('submit', async (event) => {
    event.preventDefault();
    const form = event.currentTarget;
    try {
      const data = await send('forgot-password', formData(form));
      showMessage(data.message, 'ok', $('authMessage'));
    } catch (error) {
      showMessage(error.message, 'error', $('authMessage'));
    }
  });

  $('resetForm').addEventListener('submit', async (event) => {
    event.preventDefault();
    const form = event.currentTarget;
    try {
      await send('reset-password', formData(form));
      showMessage('Contraseña actualizada. Inicia sesión.', 'ok', $('authMessage'));
      history.replaceState(null, '', 'index.html');
      form.classList.add('hidden');
    } catch (error) {
      showMessage(error.message, 'error', $('authMessage'));
    }
  });
}

function bindApp() {
  $('logoutBtn').addEventListener('click', async () => {
    await send('logout');
    location.reload();
  });

  document.querySelectorAll('.bottom-nav button').forEach((button) => {
    button.addEventListener('click', () => switchTab(button.dataset.tab));
  });

  $('onboardingWorkoutBtn').addEventListener('click', () => {
    switchTab('manageTab');
    resetWorkoutForm();
  });
  $('activeWorkoutSelect').addEventListener('change', loadActiveWorkout);
  $('trainGroupSelect').addEventListener('change', () => loadExercises('train'));
  $('trainExerciseSelect').addEventListener('change', loadExerciseSummary);
  $('historyGroupSelect').addEventListener('change', () => loadExercises('history'));
  $('historyExerciseSelect').addEventListener('change', loadHistory);
  $('showCreateExerciseBtn').addEventListener('click', () => $('quickExerciseForm').classList.remove('hidden'));
  $('cancelCreateExerciseBtn').addEventListener('click', () => $('quickExerciseForm').classList.add('hidden'));
  $('editNotesBtn').addEventListener('click', () => $('exerciseNotesForm').classList.toggle('hidden'));
  $('newWorkoutBtn').addEventListener('click', resetWorkoutForm);
  $('cancelWorkoutBtn').addEventListener('click', hideWorkoutForm);
  $('manageExerciseGroupSelect').addEventListener('change', loadManageExercises);
  $('newManageExerciseBtn').addEventListener('click', resetManageExerciseForm);
  $('cancelManageExerciseBtn').addEventListener('click', hideManageExerciseForm);
  $('cancelRecordEditBtn').addEventListener('click', hideRecordEditForm);

  $('quickExerciseForm').addEventListener('submit', createExercise);
  $('exerciseNotesForm').addEventListener('submit', saveExerciseNotes);
  $('recordForm').addEventListener('submit', saveRecord);
  $('workoutForm').addEventListener('submit', saveWorkout);
  $('exerciseManagementForm').addEventListener('submit', saveManagedExercise);
  $('recordEditForm').addEventListener('submit', saveRecordEdit);
  $('importForm').addEventListener('submit', previewImport);
  $('confirmImportBtn').addEventListener('click', confirmImport);
  $('cancelImportBtn').addEventListener('click', cancelImport);
}

function switchTab(tabId) {
  document.querySelectorAll('.screen').forEach((screen) => screen.classList.toggle('active', screen.id === tabId));
  document.querySelectorAll('.bottom-nav button').forEach((button) => button.classList.toggle('active', button.dataset.tab === tabId));
}

async function loadBootstrap() {
  const selectedManageGroup = $('manageExerciseGroupSelect').value;
  const data = await api('bootstrap');
  state.groups = data.muscle_groups;
  state.workouts = data.workouts;
  renderGroups();
  renderWorkouts();
  fillSelect($('activeWorkoutSelect'), state.workouts, 'Elige entrenamiento');
  fillSelect($('historyGroupSelect'), state.groups, 'Todos los grupos');
  fillSelect($('manageExerciseGroupSelect'), state.groups, 'Todos los grupos');
  fillSelect($('exerciseManagementForm').elements.muscle_group_id, state.groups, 'Elige grupo');
  $('onboarding').classList.toggle('hidden', state.workouts.length > 0);

  if (selectedManageGroup) $('manageExerciseGroupSelect').value = selectedManageGroup;
  await loadManageExercises();
  await loadExercises('history');

  if (state.workouts.length) {
    $('activeWorkoutSelect').value = state.workouts[0].id;
    await loadActiveWorkout();
  } else {
    fillSelect($('trainGroupSelect'), [], 'Crea un entrenamiento primero');
    fillExerciseSelect($('trainExerciseSelect'), [], 'Elige ejercicio');
  }
}

function renderGroups() {
  $('groupCheckboxes').innerHTML = state.groups.map((group) => (
    `<label class="check"><input type="checkbox" name="muscle_group_ids" value="${group.id}">${escapeHtml(group.name)}</label>`
  )).join('');
}

function renderWorkouts() {
  $('workoutList').innerHTML = state.workouts.length ? '' : '<p class="empty-state">Aún no tienes entrenamientos. Crea uno para empezar a registrar marcas.</p>';
  state.workouts.forEach((workout) => {
    const node = document.createElement('article');
    node.className = 'item';
    node.innerHTML = `
      <div class="item-main">
        <strong>${escapeHtml(workout.name)}</strong>
        <p class="muted">Entrenamiento</p>
      </div>
      <div class="action-row compact-actions">
        <button class="secondary" type="button">Editar</button>
        <button class="ghost danger" type="button">Eliminar</button>
      </div>
    `;
    node.querySelector('.secondary').addEventListener('click', () => editWorkout(workout.id));
    node.querySelector('.danger').addEventListener('click', () => deleteWorkout(workout.id));
    $('workoutList').appendChild(node);
  });
}

async function loadActiveWorkout() {
  const workoutId = $('activeWorkoutSelect').value;
  $('exercisePanel').classList.add('hidden');
  if (!workoutId) return;
  const data = await api(`workout&id=${encodeURIComponent(workoutId)}`);
  state.activeWorkout = data.workout;
  state.activeWorkoutGroups = state.groups.filter((group) => data.muscle_group_ids.includes(Number(group.id)));
  fillSelect($('trainGroupSelect'), state.activeWorkoutGroups, 'Todos los grupos del entrenamiento');
  await loadExercises('train');
}

async function loadExercises(context) {
  const groupSelect = context === 'train' ? $('trainGroupSelect') : $('historyGroupSelect');
  const exerciseSelect = context === 'train' ? $('trainExerciseSelect') : $('historyExerciseSelect');
  if (context === 'train') $('exercisePanel').classList.add('hidden');
  if (context === 'history') {
    $('historyPanel').classList.add('hidden');
    hideRecordEditForm();
  }
  const url = exercisesUrl(context, groupSelect.value);
  if (!url) {
    fillExerciseSelect(exerciseSelect, [], 'Elige ejercicio');
    return;
  }
  const data = await api(url);
  const emptyPlaceholder = data.exercises.length ? 'Elige ejercicio' : emptyExercisePlaceholder(context, groupSelect.value);
  fillExerciseSelect(exerciseSelect, data.exercises, emptyPlaceholder, !groupSelect.value);
}

function exercisesUrl(context, groupId = '') {
  if (groupId) return `exercises&muscle_group_id=${encodeURIComponent(groupId)}`;
  if (context === 'train') {
    const workoutId = $('activeWorkoutSelect').value;
    return workoutId ? `exercises&workout_id=${encodeURIComponent(workoutId)}` : '';
  }
  return 'exercises';
}

function emptyExercisePlaceholder(context, groupId = '') {
  if (context === 'train') return 'No hay ejercicios disponibles para este entrenamiento';
  return groupId ? 'No hay ejercicios en este grupo' : 'No hay ejercicios todavía';
}

async function createExercise(event) {
  event.preventDefault();
  const form = event.currentTarget;
  try {
    const payload = formData(form);
    payload.muscle_group_id = $('trainGroupSelect').value;
    if (!payload.muscle_group_id) throw new Error('Selecciona un grupo muscular');
    const data = await send('exercise', payload);
    await loadExercises('train');
    $('trainExerciseSelect').value = data.id;
    form.reset();
    form.classList.add('hidden');
    await loadExerciseSummary();
    if (!$('manageExerciseGroupSelect').value || $('manageExerciseGroupSelect').value === payload.muscle_group_id) await loadManageExercises();
  } catch (error) {
    showMessage(error.message, 'error');
  }
}

async function loadExerciseSummary() {
  const exerciseId = $('trainExerciseSelect').value;
  if (!exerciseId) return;
  const data = await api(`exercise-summary&exercise_id=${encodeURIComponent(exerciseId)}`);
  state.activeExercise = data.exercise;
  $('rmValue').textContent = formatValue(data.rm, data.exercise.metric_type);
  $('lastValue').textContent = data.last_record ? `${formatValue(data.last_record.value, data.last_record.metric_type)} · ${formatDate(data.last_record.recorded_at)}` : '-';
  $('exerciseNotes').textContent = data.exercise.notes || 'Sin notas.';
  $('exerciseNotesForm').elements.notes.value = data.exercise.notes || '';
  $('recordUnit').textContent = `(${data.exercise.metric_type})`;
  $('recordForm').reset();
  $('exercisePanel').classList.remove('hidden');
}

async function saveExerciseNotes(event) {
  event.preventDefault();
  const form = event.currentTarget;
  try {
    await send('exercise', { id: state.activeExercise.id, notes: form.elements.notes.value });
    await loadExerciseSummary();
    form.classList.add('hidden');
    await loadManageExercises();
  } catch (error) {
    showMessage(error.message, 'error');
  }
}

async function saveRecord(event) {
  event.preventDefault();
  const form = event.currentTarget;
  const savedGroupId = $('trainGroupSelect').value;
  try {
    await send('records', {
      workout_id: $('activeWorkoutSelect').value,
      exercise_id: $('trainExerciseSelect').value,
      ...formData(form),
    });
    showMessage('Registro guardado.');
    form.reset();
    state.activeExercise = null;
    $('exercisePanel').classList.add('hidden');
    $('trainGroupSelect').value = '';
    await loadExercises('train');
    if (!$('manageExerciseGroupSelect').value || $('manageExerciseGroupSelect').value === savedGroupId) await loadManageExercises();
  } catch (error) {
    showMessage(error.message, 'error');
  }
}

async function saveWorkout(event) {
  event.preventDefault();
  const form = event.currentTarget;
  try {
    const data = formData(form);
    data.muscle_group_ids = [...form.querySelectorAll('input[name="muscle_group_ids"]:checked')].map((input) => input.value);
    const action = data.id ? 'workout' : 'workouts';
    await send(action, data);
    hideWorkoutForm();
    await loadBootstrap();
    showMessage('Entrenamiento guardado.');
  } catch (error) {
    showMessage(error.message, 'error');
  }
}

async function editWorkout(id) {
  const data = await api(`workout&id=${encodeURIComponent(id)}`);
  const form = $('workoutForm');
  form.elements.id.value = data.workout.id;
  form.elements.name.value = data.workout.name;
  form.querySelectorAll('input[name="muscle_group_ids"]').forEach((input) => {
    input.checked = data.muscle_group_ids.includes(Number(input.value));
  });
  $('workoutFormTitle').textContent = 'Editar entrenamiento';
  form.classList.remove('hidden');
  switchTab('manageTab');
}

async function deleteWorkout(id) {
  if (!confirm('¿Eliminar este entrenamiento?')) return;
  try {
    await api(`workout&id=${encodeURIComponent(id)}`, { method: 'DELETE' });
    await loadBootstrap();
    showMessage('Entrenamiento eliminado.');
  } catch (error) {
    showMessage(error.message, 'error');
  }
}

function resetWorkoutForm() {
  const form = $('workoutForm');
  form.reset();
  form.elements.id.value = '';
  form.querySelectorAll('input[name="muscle_group_ids"]').forEach((input) => {
    input.checked = false;
  });
  $('workoutFormTitle').textContent = 'Nuevo entrenamiento';
  form.classList.remove('hidden');
}

function hideWorkoutForm() {
  $('workoutForm').classList.add('hidden');
}

async function loadManageExercises() {
  const groupId = $('manageExerciseGroupSelect').value;
  state.manageExercises = [];
  hideManageExerciseForm();
  const url = groupId ? `exercises&muscle_group_id=${encodeURIComponent(groupId)}` : 'exercises';
  const data = await api(url);
  state.manageExercises = data.exercises;
  renderManageExercises();
}

function renderManageExercises() {
  const emptyText = $('manageExerciseGroupSelect').value ? 'No hay ejercicios en este grupo. Crea el primero desde el botón Nuevo.' : 'No hay ejercicios todavía. Crea el primero desde el botón Nuevo.';
  $('manageExerciseList').innerHTML = state.manageExercises.length ? '' : `<p class="empty-state">${emptyText}</p>`;
  state.manageExercises.forEach((exercise) => {
    const node = document.createElement('article');
    node.className = 'item';
    const count = Number(exercise.record_count || 0);
    node.innerHTML = `
      <div class="item-main">
        <strong>${escapeHtml(exercise.name)}</strong>
        <p class="muted">${escapeHtml(groupName(exercise.muscle_group_id))} · ${exercise.metric_type} · ${count} registros</p>
      </div>
      <div class="action-row compact-actions">
        <button class="secondary" type="button">Editar</button>
        <button class="ghost danger" type="button">Eliminar</button>
      </div>
    `;
    node.querySelector('.secondary').addEventListener('click', () => editExercise(exercise.id));
    node.querySelector('.danger').addEventListener('click', () => deleteExercise(exercise.id));
    $('manageExerciseList').appendChild(node);
  });
}

function resetManageExerciseForm() {
  const form = $('exerciseManagementForm');
  form.reset();
  form.elements.id.value = '';
  form.elements.record_count.value = '0';
  form.elements.muscle_group_id.value = $('manageExerciseGroupSelect').value || '';
  form.elements.metric_type.disabled = false;
  $('exerciseMetricLockHint').classList.add('hidden');
  $('exerciseManagementTitle').textContent = 'Nuevo ejercicio';
  form.classList.remove('hidden');
}

function editExercise(id) {
  const exercise = state.manageExercises.find((item) => Number(item.id) === Number(id));
  if (!exercise) return;
  const form = $('exerciseManagementForm');
  const count = Number(exercise.record_count || 0);
  form.elements.id.value = exercise.id;
  form.elements.record_count.value = String(count);
  form.elements.name.value = exercise.name;
  form.elements.muscle_group_id.value = exercise.muscle_group_id;
  form.elements.metric_type.value = exercise.metric_type;
  form.elements.metric_type.disabled = count > 0;
  form.elements.notes.value = exercise.notes || '';
  $('exerciseMetricLockHint').classList.toggle('hidden', count < 1);
  $('exerciseManagementTitle').textContent = 'Editar ejercicio';
  form.classList.remove('hidden');
}

async function saveManagedExercise(event) {
  event.preventDefault();
  const form = event.currentTarget;
  try {
    const payload = formData(form);
    payload.metric_type = form.elements.metric_type.value;
    const previousGroup = $('manageExerciseGroupSelect').value;
    await send('exercise', payload);
    $('manageExerciseGroupSelect').value = previousGroup ? (payload.muscle_group_id || previousGroup) : '';
    await loadManageExercises();
    await refreshExerciseSelectorsAfterManagedSave(payload.muscle_group_id);
    showMessage('Ejercicio guardado.');
  } catch (error) {
    showMessage(error.message, 'error');
  }
}

async function deleteExercise(id) {
  const exercise = state.manageExercises.find((item) => Number(item.id) === Number(id));
  if (!exercise) return;

  const count = Number(exercise.record_count || 0);
  const message = `¿Eliminar "${exercise.name}"? Si eliminas este ejercicio, también se eliminarán todos sus registros de marcas asociados (${count}).`;
  if (!confirm(message)) return;

  try {
    await api(`exercise&id=${encodeURIComponent(id)}`, { method: 'DELETE' });
    hideManageExerciseForm();
    clearDeletedExerciseSelections(id);
    await loadManageExercises();
    showMessage('Ejercicio eliminado.');
  } catch (error) {
    showMessage(error.message, 'error');
  }
}

function clearDeletedExerciseSelections(id) {
  if ($('trainExerciseSelect').value === String(id)) {
    state.activeExercise = null;
    $('exercisePanel').classList.add('hidden');
    $('recordForm').reset();
    $('trainExerciseSelect').value = '';
  }
  if ($('historyExerciseSelect').value === String(id)) {
    $('historyExerciseSelect').value = '';
    $('historyPanel').classList.add('hidden');
    hideRecordEditForm();
  }
}

async function refreshExerciseSelectorsAfterManagedSave(groupId) {
  if (!$('trainGroupSelect').value || $('trainGroupSelect').value === groupId) await loadExercises('train');
  if (!$('historyGroupSelect').value || $('historyGroupSelect').value === groupId) await loadExercises('history');
}

function hideManageExerciseForm() {
  $('exerciseManagementForm').classList.add('hidden');
}

async function previewImport(event) {
  event.preventDefault();
  const form = event.currentTarget;
  const file = $('importFileInput').files[0];
  if (!file) {
    showMessage('Selecciona un archivo para importar.', 'error');
    return;
  }

  try {
    const payload = new FormData(form);
    const response = await fetch('api.php?action=import-preview', {
      method: 'POST',
      credentials: 'same-origin',
      headers: state.csrfToken ? { 'X-CSRF-Token': state.csrfToken } : {},
      body: payload,
    });
    const data = await response.json();
    if (!data.ok) throw new Error(data.error || 'Error inesperado');
    state.importPreviewToken = data.import_token || null;
    renderImportPreview(data);
  } catch (error) {
    state.importPreviewToken = null;
    renderImportPreview({ summary: null, errors: [error.message], warnings: [] });
    showMessage(error.message, 'error');
  }
}

function renderImportPreview(data) {
  const summary = data.summary || {};
  const errors = data.errors || [];
  const warnings = data.warnings || [];
  $('importPreviewPanel').classList.remove('hidden');
  $('importSummary').innerHTML = [
    ['Entrenamientos', summary.workouts || 0],
    ['Ejercicios', summary.exercises || 0],
    ['Registros', summary.records || 0],
    ['Errores', errors.length],
  ].map(([label, value]) => `<div class="summary-item"><span class="label">${label}</span><strong>${value}</strong></div>`).join('');
  $('importWarnings').innerHTML = warnings.length ? `<p class="muted">${warnings.map(escapeHtml).join('<br>')}</p>` : '';
  $('importErrors').innerHTML = errors.length ? `<p>${errors.map(escapeHtml).join('<br>')}</p>` : '';
  $('confirmImportBtn').disabled = errors.length > 0 || !state.importPreviewToken;
}

async function confirmImport() {
  if (!state.importPreviewToken) return;
  try {
    const data = await send('import-confirm', { import_token: state.importPreviewToken });
    state.importPreviewToken = null;
    $('importForm').reset();
    $('importPreviewPanel').classList.add('hidden');
    await loadBootstrap();
    showMessage(importSummaryMessage(data.summary));
  } catch (error) {
    showMessage(error.message, 'error');
  }
}

async function cancelImport() {
  try {
    await send('import-cancel');
  } catch (error) {
    showMessage(error.message, 'error');
  }
  state.importPreviewToken = null;
  $('importForm').reset();
  $('importPreviewPanel').classList.add('hidden');
}

function importSummaryMessage(summary = {}) {
  return `Importacion aplicada: ${summary.workouts || 0} entrenamientos, ${summary.exercises || 0} ejercicios, ${summary.records || 0} registros.`;
}

async function loadHistory() {
  const exerciseId = $('historyExerciseSelect').value;
  hideRecordEditForm();
  if (!exerciseId) return;
  const data = await api(`history&exercise_id=${encodeURIComponent(exerciseId)}`);
  renderHistory(data);
}

function renderHistory(data) {
  $('historyPanel').classList.remove('hidden');
  const hasRecords = data.records.length > 0;
  $('historyEmptyState').classList.toggle('hidden', hasRecords);
  $('historyChart').classList.toggle('hidden', !hasRecords);
  renderHistoryCards(data.records);
  const rows = data.records.map((record) => `
    <tr>
      <td>${formatDate(record.recorded_at)}</td>
      <td>${escapeHtml(record.workout_name)}</td>
      <td>${formatValue(record.value, record.metric_type)}</td>
      <td>${escapeHtml(record.note || '')}</td>
      <td>
        <button class="ghost" type="button" data-edit="${record.id}" data-value="${record.value}" data-note="${escapeHtml(record.note || '')}">Editar</button>
        <button class="ghost danger" type="button" data-delete="${record.id}">Eliminar</button>
      </td>
    </tr>
  `).join('');
  $('historyRows').innerHTML = rows || '<tr><td colspan="5">Sin registros.</td></tr>';
  bindHistoryActions($('historyRows'));
  renderChart(data.chart);
}

function renderHistoryCards(records) {
  $('historyCards').innerHTML = records.map((record) => `
    <article class="record-card">
      <div>
        <span class="label">${formatDate(record.recorded_at)}</span>
        <strong>${formatValue(record.value, record.metric_type)}</strong>
      </div>
      <p class="muted">${escapeHtml(record.workout_name)}${record.note ? ` · ${escapeHtml(record.note)}` : ''}</p>
      <div class="action-row compact-actions">
        <button class="ghost" type="button" data-edit="${record.id}" data-value="${record.value}" data-note="${escapeHtml(record.note || '')}">Editar</button>
        <button class="ghost danger" type="button" data-delete="${record.id}">Eliminar</button>
      </div>
    </article>
  `).join('');
  bindHistoryActions($('historyCards'));
}

function bindHistoryActions(container) {
  container.querySelectorAll('[data-edit]').forEach((button) => button.addEventListener('click', () => editRecord(button)));
  container.querySelectorAll('[data-delete]').forEach((button) => button.addEventListener('click', () => deleteRecord(button.dataset.delete)));
}

function renderChart(points) {
  if (!window.Chart) {
    $('historyEmptyState').classList.remove('hidden');
    $('historyEmptyState').textContent = 'El gráfico no está disponible sin conexión a Chart.js.';
    return;
  }
  const ctx = $('historyChart');
  if (state.chart) state.chart.destroy();
  state.chart = new Chart(ctx, {
    type: 'line',
    data: {
      labels: points.map((point) => point.day),
      datasets: [{ label: 'Mejor marca diaria', data: points.map((point) => Number(point.value)), borderColor: '#147a5b', backgroundColor: 'rgba(20,122,91,0.12)', tension: 0.25, fill: true }],
    },
    options: { responsive: true, plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true } } },
  });
}

function editRecord(button) {
  const form = $('recordEditForm');
  form.elements.id.value = button.dataset.edit;
  form.elements.value.value = button.dataset.value;
  form.elements.note.value = button.dataset.note || '';
  form.classList.remove('hidden');
  form.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
}

async function saveRecordEdit(event) {
  event.preventDefault();
  const form = event.currentTarget;
  try {
    await send('records', formData(form));
    hideRecordEditForm();
    await loadHistory();
    showMessage('Registro actualizado.');
  } catch (error) {
    showMessage(error.message, 'error');
  }
}

function hideRecordEditForm() {
  $('recordEditForm').classList.add('hidden');
}

async function deleteRecord(id) {
  if (!confirm('¿Eliminar este registro?')) return;
  await api(`records&id=${encodeURIComponent(id)}`, { method: 'DELETE' });
  hideRecordEditForm();
  await loadHistory();
}

function groupName(id) {
  const group = state.groups.find((item) => Number(item.id) === Number(id));
  return group ? group.name : 'Grupo';
}

function formatDate(value) {
  return new Date(value.replace(' ', 'T')).toLocaleString('es-ES', { dateStyle: 'short', timeStyle: 'short' });
}

function escapeHtml(value) {
  return String(value).replace(/[&<>"']/g, (char) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' }[char]));
}

init().catch((error) => {
  showAuth();
  showMessage(error.message, 'error', $('authMessage'));
});

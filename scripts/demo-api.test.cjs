// Run against php -S 127.0.0.1:4178 -t public (does not use MySQL).
const assert = require('node:assert/strict');
const base = process.env.TEST_BASE_URL || 'http://127.0.0.1:4178/api.php';
function client() {
  let cookie = '', csrf = '';
  return async function request(action, payload, method = payload === undefined ? 'GET' : 'POST', expected = 200) {
    const response = await fetch(`${base}?action=${action}`, {
      method, headers: { Cookie: cookie, 'Content-Type': 'application/json', 'X-CSRF-Token': csrf },
      ...(payload === undefined ? {} : { body: JSON.stringify(payload) }),
    });
    if (response.headers.get('set-cookie')) cookie = response.headers.get('set-cookie').split(';')[0];
    const text = await response.text();
    assert.equal(response.status, expected, `${action}: ${text}`);
    const data = response.headers.get('content-type')?.includes('application/json') ? JSON.parse(text) : text;
    if (data.csrf_token) csrf = data.csrf_token;
    return data;
  };
}
(async () => {
  const a = client(), b = client();
  await b('demo-start', {}, 'POST', 403); // Demo entry must not bypass CSRF.
  assert.equal((await a('me')).user, null);
  await a('demo-start', {});
  assert.equal((await a('me')).user.is_demo, true);
  const bootstrap = await a('bootstrap');
  const legs = bootstrap.workouts.find(w => w.name === 'Piernas').id;
  const initial = (await a(`workout&id=${legs}`)).exercise_ids;
  const exercise = (await a('exercise', { name: 'Máquina de prueba', metric_type: 'kg' })).id;
  await a('exercise', { name: 'Máquina de prueba', metric_type: 'kg' }, 'POST', 409);
  assert.equal((await a(`exercises&muscle_group_id=none`)).exercises.some(e => e.id === exercise), true);
  // An external exercise is valid, but the selected routine must not be attached implicitly.
  await a('records', { exercise_id: exercise, workout_id: legs, value: 25 }, 'POST', 400);
  await a('records', { exercise_id: exercise, value: 25 });
  let history = await a(`history&exercise_id=${exercise}`);
  assert.equal(history.records[0].workout_name, null);
  assert.deepEqual((await a(`workout&id=${legs}`)).exercise_ids, initial);
  await a('workout', { id: legs, name: 'Piernas', exercise_ids: [...initial, exercise] });
  await a('records', { exercise_id: exercise, workout_id: legs, value: 30 });
  const routine2 = (await a('workouts', { name: 'Prueba', exercise_ids: [exercise] })).id;
  await a('exercise', { id: exercise, name: 'Máquina de prueba', metric_type: 'kg', muscle_group_id: 1 });
  assert.equal((await a(`exercises&workout_id=${legs}&muscle_group_id=1`)).exercises.some(e => e.id === exercise), true);
  assert.deepEqual((await a(`workout&id=${routine2}`)).exercise_ids, [exercise]);
  await a('workout', { id: legs, name: 'Piernas', exercise_ids: initial });
  assert.equal((await a(`history&exercise_id=${exercise}`)).records.length, 2);
  await a(`workout&id=${legs}`, undefined, 'DELETE');
  history = await a(`history&exercise_id=${exercise}`);
  assert.equal(history.records.length, 2);
  assert.equal(history.records.every(r => r.workout_name === null), true);
  await a('exercise', { name: 'Libre exportable', metric_type: 'reps' });
  const backup = await a('export');
  assert.equal(backup.version, 3);
  assert.ok(backup.exercises.some(e => e.muscle_group === null));
  assert.ok(backup.workouts.some(w => w.exercises.length > 0));
  // Repeated preview/confirmation must restore and merge without duplicating free marks.
  const preview = await a('import-preview', { filename: 'backup.json', content: JSON.stringify(backup) });
  assert.deepEqual(preview.errors, []);
  await a('import-confirm', { import_token: preview.import_token });
  assert.equal((await a(`history&exercise_id=${exercise}`)).records.length, 2);
  await a('import-confirm', { import_token: preview.import_token }, 'POST', 400);
  await b('me'); await b('demo-start', {});
  assert.equal((await b('exercises')).exercises.some(e => e.name === 'Máquina de prueba'), false);
  // The exact fresh-account workflow: no exercises, no routines, no group.
  for (const e of (await b('exercises')).exercises) await b(`exercise&id=${e.id}`, undefined, 'DELETE');
  for (const w of (await b('bootstrap')).workouts) await b(`workout&id=${w.id}`, undefined, 'DELETE');
  assert.equal((await b('bootstrap')).workouts.length, 0);
  const first = (await b('exercise', { name: 'Primer ejercicio', metric_type: 'reps', muscle_group_id: null })).id;
  await b('records', { exercise_id: first, workout_id: null, value: 10 });
  assert.equal((await b(`history&exercise_id=${first}`)).records.length, 1);
  const emptyRoutine = (await b('workouts', { name: 'Vacía', exercise_ids: [] })).id;
  assert.deepEqual((await b(`workout&id=${emptyRoutine}`)).exercise_ids, []);
  await b('exercise', { id: first, name: 'Primer ejercicio', metric_type: 'kg' }, 'POST', 409);
  const csv = await b('import-preview', { filename: 'free.csv', content: 'muscle_group,name,metric_type,notes\n,CSV libre,min,' });
  assert.deepEqual(csv.errors, []);
  await b('import-confirm', { import_token: csv.import_token });
  assert.ok((await b('exercises')).exercises.some(e => e.name === 'CSV libre' && e.muscle_group_id === null));
  assert.match(await b('export&format=csv&type=workouts'), /exercises/);
  const gym = (await b('gyms', { name: 'Pruebas' })).id;
  await b('preferences', { gyms_enabled: true });
  await b('records', { exercise_id: first, value: 11 }, 'POST', 400);
  await b('records', { exercise_id: first, gym_id: gym, value: 11 });
  assert.equal((await b(`history&exercise_id=${first}&gym_id=${gym}`)).records.length, 1);
  await b('login', { email: 'demo@example.test', password: 'not-a-real-account' }, 'POST', 409);
  await a('demo-reset', {});
  assert.equal((await a('exercises')).exercises.some(e => e.name === 'Máquina de prueba'), false);
  const legacy = { schema: 'gym-tracker-export', version: 1,
    workouts: [{ name: 'Legacy', muscle_groups: ['Pectoral'] }],
    exercises: [{ name: 'Press legacy', muscle_group: 'Pectoral', metric_type: 'kg' }], records: [] };
  const oldPreview = await a('import-preview', { filename: 'v1.json', content: JSON.stringify(legacy) });
  assert.deepEqual(oldPreview.errors, []);
  await a('import-confirm', { import_token: oldPreview.import_token });
  const imported = (await a('bootstrap')).workouts.find(w => w.name === 'Legacy');
  assert.ok((await a(`exercises&workout_id=${imported.id}`)).exercises.some(e => e.name === 'Press legacy'));
  await a('workouts', { name: 'Ajeno', exercise_ids: [999999] }, 'POST', 404);
  await a('logout', {});
  assert.equal((await a('me')).user, null);
  await a('exercises', undefined, 'GET', 401);
  console.log('Demo API: isolation, free marks, lists, backups v1/v3, reset and logout passed.');
})().catch(error => { console.error(error); process.exitCode = 1; });

// Run the real Spark commands in testing mode. No HTTP requests or database connection.
import {execFileSync} from 'node:child_process';
import {fileURLToPath} from 'node:url';
import assert from 'node:assert/strict';

const cwd = fileURLToPath(new URL('..', import.meta.url));
const php = process.env.PLAYER_PHP || 'php';
const spark = (...args) => execFileSync(php, ['-r', 'define("SUPPORTPATH", getcwd() . "/tests/_support/"); require "spark";', '--', ...args],
  {cwd, env: {...process.env, CI_ENVIRONMENT: 'testing'}, encoding: 'utf8'});
const publicRoutes = ['/q/example', '/q/example/play', '/q/example/results', '/media/example'].map(route => ['GET', route, 'public']);
const studentRoutes = [
  ['GET', 'tickets/example'], ['POST', 'starts'], ['GET', 'assessments/example'], ['POST', 'assessments/example/sync'],
  ['GET', 'assessments/example/results'], ['POST', 'assessments/example/events'], ['POST', 'assessments/example/media'], ['POST', 'practice/media'],
].map(([method, route]) => [method, '/api/v1/player/' + route, 'student']);
const teacherRoutes = [
  ...['/dashboard', '/quizzes', '/quizzes/archived', '/quizzes/trash', '/quizzes/example/edit', '/api/v1/csrf', '/api/v1/quizzes/example'].map(route => ['GET', route]),
  ['POST', '/api/v1/quizzes'], ['PUT', '/api/v1/quizzes/example'], ['POST', '/logout'],
  ...['publish', 'close', 'reopen', 'archive', 'unarchive', 'trash', 'restore', 'duplicate'].map(action => ['POST', '/api/v1/quizzes/example/' + action]),
  ...['cover', 'questions/1/media', 'options/1/media'].flatMap(target => ['POST', 'DELETE'].map(method => [method, '/api/v1/quizzes/example/' + target])),
].map(([method, route]) => [method, route, 'teacher']);
const authRoutes = ['/login', '/register'].flatMap(route => ['GET', 'POST'].map(method => [method, route, 'auth']));
const listing = spark('routes');
assert.match(listing, /Student\\PlayerController/);
for (const [method, route, kind] of [...publicRoutes, ...studentRoutes, ...teacherRoutes, ...authRoutes]) {
  const output = spark('filter:check', method, route);
  const classes = output.split('Before Filter Classes:')[1]?.split('After Filter Classes:')[0] || '';
  assert.ok(classes, `${method} ${route} did not resolve filters`);
  assert.equal(classes.includes('PlayerRequestFilter'), kind === 'student', `${route}: student filter`);
  assert.equal(classes.includes('CSRF'), ['teacher', 'auth'].includes(kind), `${route}: CSRF boundary`);
  assert.equal(classes.includes('AuthFilter'), kind === 'teacher', `${route}: teacher authorization`);
}
console.log(`Spark routes and ${publicRoutes.length + studentRoutes.length + teacherRoutes.length + authRoutes.length} route/filter checks passed (testing environment).`);

import fs from 'node:fs/promises';
import os from 'node:os';
import path from 'node:path';
import {spawn} from 'node:child_process';

const pause = milliseconds => new Promise(resolve => setTimeout(resolve, milliseconds));

export async function launchBrowser() {
  const chromePath = process.env.PLAYER_CHROME || '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome';
  const artifacts = await fs.mkdtemp(path.join(os.tmpdir(), '123test-browser-'));
  const profile = path.join(artifacts, 'profile');
  const browser = spawn(chromePath, [
    '--headless=new', '--disable-gpu', '--no-first-run', '--no-default-browser-check',
    '--remote-debugging-port=0', `--user-data-dir=${profile}`, 'about:blank',
  ], {stdio:'ignore'});

  let port;
  await until(async () => {
    try {
      port = (await fs.readFile(path.join(profile, 'DevToolsActivePort'), 'utf8')).split('\n')[0];
      return Boolean(port);
    } catch (_) { return false; }
  }, 'Chrome startup');

  const version = await (await fetch(`http://127.0.0.1:${port}/json/version`)).json();
  const socket = new WebSocket(version.webSocketDebuggerUrl);
  await new Promise((resolve, reject) => {
    socket.addEventListener('open', resolve, {once:true});
    socket.addEventListener('error', reject, {once:true});
  });

  const pending = new Map();
  const errors = [];
  let sequence = 0;
  let sessionId;
  socket.addEventListener('message', event => {
    const message = JSON.parse(event.data);
    if (message.id) {
      const handler = pending.get(message.id);
      pending.delete(message.id);
      if (message.error) handler?.reject(new Error(message.error.message));
      else handler?.resolve(message.result);
      return;
    }
    if (message.method === 'Runtime.exceptionThrown') {
      errors.push(message.params.exceptionDetails.exception?.description || message.params.exceptionDetails.text);
    }
    if (message.method === 'Page.javascriptDialogOpening') {
      command('Page.handleJavaScriptDialog', {accept:true}, message.sessionId).catch(() => {});
    }
  });

  function command(method, params = {}, session = sessionId) {
    const id = ++sequence;
    return new Promise((resolve, reject) => {
      pending.set(id, {resolve,reject});
      socket.send(JSON.stringify({id,method,params,...(session ? {sessionId:session} : {})}));
    });
  }

  const target = await command('Target.createTarget', {url:'about:blank'}, null);
  sessionId = (await command('Target.attachToTarget', {targetId:target.targetId,flatten:true}, null)).sessionId;
  await command('Page.enable');
  await command('Runtime.enable');
  await command('Network.enable');

  async function evaluate(expression) {
    const response = await command('Runtime.evaluate', {expression,returnByValue:true,awaitPromise:true});
    if (response.exceptionDetails) throw new Error(response.exceptionDetails.exception?.description || response.exceptionDetails.text);
    return response.result.value;
  }

  async function navigate(url) {
    await command('Page.navigate', {url});
  }

  async function close() {
    try { socket.close(); } catch (_) {}
    browser.kill('SIGTERM');
    await new Promise(resolve => browser.once('exit', resolve));
    await fs.rm(artifacts, {recursive:true,force:true});
  }

  return {command,evaluate,navigate,until,errors,close};
}

export async function until(check, label, timeout = 15000) {
  const end = Date.now() + timeout;
  while (Date.now() < end) {
    if (await check()) return;
    await pause(100);
  }
  throw new Error(`Timed out: ${label}`);
}

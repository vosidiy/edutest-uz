import assert from 'node:assert/strict';
import fs from 'node:fs';
import test from 'node:test';
import vm from 'node:vm';

const source = fs.readFileSync(new URL('../../public/js/auth.js', import.meta.url), 'utf8');
const window = {};
vm.runInNewContext(source, {
  window,
  document: {readyState: 'loading', addEventListener() {}},
});

const {looksLikeGibberish, validateProfile, validateRegistration, validatePasswordChange} = window.EduTestAuthValidation;
const valid = {
  name: 'Ada Lovelace',
  email: 'ada@example.com',
  phone: '+998901234567',
  password: 'secret1',
  passwordConfirm: 'secret1',
};

test('detects obvious gibberish without rejecting ordinary names', () => {
  assert.equal(looksLikeGibberish('asdf'), true);
  assert.equal(looksLikeGibberish('asdadsasd'), true);
  assert.equal(looksLikeGibberish('Anna'), false);
  assert.equal(looksLikeGibberish('Иван'), false);
});

test('accepts a valid registration and optional empty phone', () => {
  assert.equal(validateRegistration(valid), null);
  assert.equal(validateRegistration({...valid, phone: ''}), null);
  assert.equal(validateProfile(valid), null);
});

test('rejects invalid names and emails', () => {
  assert.equal(validateRegistration({...valid, name: 'a'}).field, 'name');
  assert.equal(validateRegistration({...valid, name: 'aaa'}).field, 'name');
  assert.equal(validateRegistration({...valid, email: 'asdadsasd@example.com'}).field, 'email');
});

test('requires an exact Uzbek phone format and non-repeating subscriber digits', () => {
  for (const phone of ['111111111', '000000000', '+998123', '+99812345678', '+9981234567890', '+99890ABC4567', '+99890-123-456']) {
    assert.equal(validateRegistration({...valid, phone}).field, 'phone');
  }

  assert.equal(validateRegistration({...valid, phone: '+998901234567'}), null);
});

test('validates password length and confirmation', () => {
  assert.equal(validateRegistration({...valid, password: '12345'}).field, 'password');
  assert.equal(validateRegistration({...valid, passwordConfirm: 'different'}).field, 'passwordConfirm');
  assert.equal(validatePasswordChange({newPassword: '12345', newPasswordConfirm: '12345'}).field, 'newPassword');
  assert.equal(validatePasswordChange({newPassword: 'secret1', newPasswordConfirm: 'different'}).field, 'newPasswordConfirm');
  assert.equal(validatePasswordChange({newPassword: 'secret1', newPasswordConfirm: 'secret1'}), null);
});

const view = document.querySelector('#view');
const pageTitle = document.querySelector('#page-title');
const pageEyebrow = document.querySelector('#page-eyebrow');
const topbar = document.querySelector('.topbar');
const toast = document.querySelector('#toast');

const svg = {
  quiz: '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M7 3h10v2h3v16H4V5h3V3Zm2 4h6V5H9v2Z"/></svg>',
  users: '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2m7-10a4 4 0 1 0 0-8 4 4 0 0 0 0 8Zm13 10v-2a4 4 0 0 0-3-3.87m-2-12a4 4 0 0 1 0 7.75"/></svg>',
  score: '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="m4 14 5-5 4 4 7-8m0 0v5m0-5h-5"/></svg>',
  clock: '<svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg>',
  search: '<svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m20 20-4-4"/></svg>',
  edit: '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="m4 20 4-1 11-11-3-3L5 16l-1 4Zm10-13 3 3"/></svg>',
  eye: '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M2 12s4-7 10-7 10 7 10 7-4 7-10 7S2 12 2 12Z"/><circle cx="12" cy="12" r="3"/></svg>',
  results: '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 3h14v18H5V3Zm3 13v2h2v-2H8Zm0-5v4h2v-4H8Zm0-5v4h2V6H8Zm5 8v4h3v-4h-3Zm0-8v7h3V6h-3Z"/></svg>',
  chevron: '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="m9 18 6-6-6-6"/></svg>',
  back: '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="m15 18-6-6 6-6"/></svg>',
  plus: '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 5v14M5 12h14"/></svg>',
  check: '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="m5 12 4 4L19 6"/></svg>',
  trash: '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 7h16M9 7V4h6v3m3 0-1 13H7L6 7m4 4v5m4-5v5"/></svg>',
  image: '<svg viewBox="0 0 24 24" aria-hidden="true"><rect x="3" y="4" width="18" height="16" rx="2"/><circle cx="9" cy="10" r="2"/><path d="m21 15-5-5L5 20"/></svg>',
  audio: '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M9 18V5l10-2v13M9 9l10-2M6 21a3 3 0 1 0 0-6 3 3 0 0 0 0 6Zm10-2a3 3 0 1 0 0-6 3 3 0 0 0 0 6Z"/></svg>',
  video: '<svg viewBox="0 0 24 24" aria-hidden="true"><rect x="3" y="5" width="14" height="14" rx="2"/><path d="m17 10 4-2v8l-4-2"/></svg>',
  download: '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 3v12m0 0 4-4m-4 4-4-4M5 20h14"/></svg>',
  shield: '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 3 5 6v5c0 5 3 8 7 10 4-2 7-5 7-10V6l-7-3Zm-3 9 2 2 4-4"/></svg>',
  alert: '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 3 2 20h20L12 3Zm0 6v5m0 3v.1"/></svg>',
  spark: '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="m12 3 1.4 4.6L18 9l-4.6 1.4L12 15l-1.4-4.6L6 9l4.6-1.4L12 3Zm7 11 .8 2.2L22 17l-2.2.8L19 20l-.8-2.2L16 17l2.2-.8L19 14ZM5 14l.9 2.1L8 17l-2.1.9L5 20l-.9-2.1L2 17l2.1-.9L5 14Z"/></svg>'
};

const quizQuestions = [
  { type: 'Single choice', title: 'What is the main purpose of the passage?', points: 2, timer: 45, options: ['To explain a natural process', 'To compare two research methods', 'To describe a historical event', 'To argue for policy change'], correct: [0], explanation: 'The passage mainly explains how seasonal migration develops.' },
  { type: 'Multiple choice', title: 'Which two statements are supported by the speaker?', points: 3, timer: 60, options: ['The trial lasted six months', 'Participants worked in teams', 'Results were published online', 'The control group was larger'], correct: [0, 2], explanation: 'Both the trial duration and publication method are stated explicitly.' },
  { type: 'Short answer', title: 'Complete the sentence with one word.', points: 1, timer: 30, options: ['migration'], correct: [0], explanation: 'The accepted answer is “migration”.' },
  { type: 'Single choice', title: 'Choose the best heading for paragraph four.', points: 2, timer: 45, options: ['Early objections', 'A practical breakthrough', 'Unexpected costs', 'Future applications'], correct: [1], explanation: 'Paragraph four focuses on the first usable implementation.' }
];

let selectedQuestion = 0;
const playerState = { current: 0, answers: [[], [], ''], remaining: 1800, interval: null };

function showToast(message) {
  toast.textContent = message;
  toast.classList.add('show');
  clearTimeout(showToast.timer);
  showToast.timer = setTimeout(() => toast.classList.remove('show'), 2400);
}

function setAdminHeader(eyebrow, title, active) {
  document.body.classList.remove('student-mode');
  topbar.style.display = '';
  pageEyebrow.textContent = eyebrow;
  pageTitle.textContent = title;
  document.querySelectorAll('.nav-item').forEach(item => item.classList.toggle('active', item.dataset.route === active));
}

function dashboardTemplate() {
  return `
    <div class="hero-strip"><div class="hero-copy"><p class="kicker">Ready when you are</p><h2>Build your next assessment in minutes.</h2><p>Create focused, one-question-at-a-time quizzes with flexible timers and automatic scoring.</p><a class="button" href="#builder">Create a quiz</a></div></div>
    <div class="metrics" aria-label="Workspace summary">
      <article class="metric"><div class="metric-top"><span>Total quizzes</span><span class="metric-icon">${svg.quiz}</span></div><p class="metric-value">12</p><span class="metric-note"><strong>+2</strong> this month</span></article>
      <article class="metric"><div class="metric-top"><span>Student attempts</span><span class="metric-icon blue">${svg.users}</span></div><p class="metric-value">486</p><span class="metric-note"><strong>+18%</strong> from August</span></article>
      <article class="metric"><div class="metric-top"><span>Average score</span><span class="metric-icon amber">${svg.score}</span></div><p class="metric-value">78%</p><span class="metric-note">Across all published quizzes</span></article>
      <article class="metric"><div class="metric-top"><span>Active now</span><span class="metric-icon rose">${svg.clock}</span></div><p class="metric-value">24</p><span class="metric-note">Students taking a quiz</span></article>
    </div>
    <div class="dashboard-grid">
      <section class="panel"><div class="panel-head"><h2>Recent quizzes</h2><a class="text-link" href="#quizzes">View all →</a></div><div class="table-wrap"><table class="quiz-table"><thead><tr><th>Quiz</th><th>Status</th><th>Attempts</th><th>Average</th></tr></thead><tbody>
        <tr data-link="#builder"><td><span class="quiz-name"><strong>IELTS Academic Reading</strong><small>Updated 2 hours ago</small></span></td><td><span class="status">Published</span></td><td>128</td><td class="score">82%</td></tr>
        <tr data-link="#results"><td><span class="quiz-name"><strong>Frontend Developer Screening</strong><small>Updated yesterday</small></span></td><td><span class="status">Published</span></td><td>64</td><td class="score">71%</td></tr>
        <tr data-link="#builder"><td><span class="quiz-name"><strong>Algebra: Linear Equations</strong><small>Updated 3 days ago</small></span></td><td><span class="status draft">Draft</span></td><td>—</td><td class="score">—</td></tr>
      </tbody></table></div></section>
      <aside class="panel"><div class="panel-head"><h2>Latest activity</h2></div><div class="activity-list"><div class="activity"><span class="activity-badge">SA</span><div><p><strong>Sardor A.</strong> completed IELTS Academic Reading</p><small>8 minutes ago · 86%</small></div></div><div class="activity"><span class="activity-badge">MN</span><div><p><strong>Madina N.</strong> completed Algebra: Linear Equations</p><small>21 minutes ago · 74%</small></div></div><div class="activity"><span class="activity-badge">+8</span><div><p>Eight more submissions arrived this morning</p><small>View them in Results</small></div></div></div></aside>
    </div>`;
}

function renderDashboard() {
  setAdminHeader('Thursday, 17 September', 'Good morning, Aziza', 'dashboard');
  view.innerHTML = dashboardTemplate();
  view.querySelectorAll('[data-link]').forEach(row => row.addEventListener('click', () => location.hash = row.dataset.link));
}

const quizRows = [
  ['IELTS Academic Reading', '16 questions · 35 min', 'Published', '128', '82%', 'teal'],
  ['Frontend Developer Screening', '20 questions · 25 min', 'Published', '64', '71%', ''],
  ['Algebra: Linear Equations', '12 questions · No limit', 'Draft', '—', '—', 'amber'],
  ['Workplace Safety Refresher', '10 questions · 15 min', 'Closed', '214', '89%', '']
];

function renderQuizzes() {
  setAdminHeader('Quiz library', 'My quizzes', 'quizzes');
  view.innerHTML = `
    <div class="section-heading"><div><h2>Assessments</h2><p>Create, publish, and manage every quiz in one place.</p></div><div class="section-actions"><button class="button secondary" data-demo="import">${svg.spark} Import with AI</button><a class="button primary" href="#builder">${svg.plus} New quiz</a></div></div>
    <div class="toolbar"><label class="search-field">${svg.search}<input id="quiz-search" type="search" placeholder="Search quizzes" aria-label="Search quizzes"></label><select class="compact-select" aria-label="Filter quizzes"><option>All statuses</option><option>Published</option><option>Draft</option><option>Closed</option></select><select class="compact-select" aria-label="Sort quizzes"><option>Recently updated</option><option>Most attempts</option><option>Title A–Z</option></select></div>
    <div class="quiz-list-panel"><div class="quiz-row quiz-row-head"><span>Quiz</span><span>Status</span><span>Attempts</span><span>Average</span><span></span></div><div id="quiz-rows">
      ${quizRows.map((q, i) => `<div class="quiz-row" data-title="${q[0].toLowerCase()}"><div class="quiz-title-cell"><span class="quiz-symbol ${q[5]}">${svg.quiz}</span><span><strong>${q[0]}</strong><small>${q[1]}</small></span></div><span><span class="badge ${q[2] === 'Published' ? 'live' : q[2] === 'Draft' ? 'warning' : ''}">${q[2]}</span></span><span class="quiz-stat">${q[3]}</span><span class="quiz-stat"><strong>${q[4]}</strong></span><span class="quiz-actions"><button class="more-button" data-go="${i === 0 ? '#student-start' : '#results'}" aria-label="${i === 0 ? 'Preview quiz' : 'View results'}">${i === 0 ? svg.eye : svg.results}</button><button class="more-button" data-go="#builder" aria-label="Edit quiz">${svg.edit}</button></span></div>`).join('')}
    </div></div>`;
  document.querySelector('#quiz-search').addEventListener('input', event => { const query = event.target.value.trim().toLowerCase(); view.querySelectorAll('#quiz-rows .quiz-row').forEach(row => row.hidden = !row.dataset.title.includes(query)); });
  view.querySelectorAll('[data-go]').forEach(button => button.addEventListener('click', () => location.hash = button.dataset.go));
  view.querySelector('[data-demo="import"]').addEventListener('click', () => showToast('AI import is planned for a later phase.'));
}

function answerRows(question) {
  if (question.type === 'Short answer') return `<div class="field"><label>Accepted answers</label><input type="text" value="${question.options.join(', ')}"><span class="hint">Separate alternatives with commas. Matching ignores capitalization and extra spaces.</span></div>`;
  return question.options.map((option, index) => `<div class="answer-row"><button class="answer-correct ${question.correct.includes(index) ? 'selected' : ''}" type="button" data-correct="${index}" aria-label="Mark answer ${index + 1} as correct">${svg.check}</button><input type="text" value="${option}" aria-label="Answer ${index + 1}"><button class="delete-answer" type="button" data-delete-option="${index}" aria-label="Delete answer ${index + 1}">${svg.trash}</button></div>`).join('');
}

function renderBuilder() {
  setAdminHeader('Quiz creation', 'Quiz builder', 'quizzes');
  const q = quizQuestions[selectedQuestion];
  view.innerHTML = `<div class="builder-view"><div class="builder-bar"><div class="builder-name"><button class="back" data-go="#quizzes" aria-label="Back to quizzes">${svg.back}</button><span><strong>IELTS Academic Reading</strong><small>Draft saved just now</small></span></div><div class="builder-actions"><button class="button ghost small" data-preview>${svg.eye} Preview</button><button class="button secondary small" data-save>Save draft</button><button class="button primary small" data-publish>Publish quiz</button></div></div>
    <div class="builder-layout"><aside class="question-rail"><div class="rail-heading"><strong>Questions</strong><span class="badge">${quizQuestions.length}</span></div><div class="question-list">${quizQuestions.map((item, index) => `<button class="question-item ${index === selectedQuestion ? 'active' : ''}" data-question="${index}"><span class="question-number">${index + 1}</span><span class="question-label"><strong>${item.title}</strong><small>${item.type} · ${item.points} pt</small></span>${svg.chevron}</button>`).join('')}</div><button class="button secondary small rail-add" data-add-question>${svg.plus} Add question</button></aside>
      <section class="editor-canvas"><div class="editor-paper"><div class="editor-paper-head"><span>Question ${selectedQuestion + 1} of ${quizQuestions.length}</span><span>${q.points} points</span></div><div class="question-editor"><div class="editor-grid"><div class="field question-prompt"><label for="question-title">Question</label><textarea id="question-title">${q.title}</textarea></div><div class="field"><label for="question-type">Answer type</label><select id="question-type"><option ${q.type === 'Single choice' ? 'selected' : ''}>Single choice</option><option ${q.type === 'Multiple choice' ? 'selected' : ''}>Multiple choice</option><option ${q.type === 'Short answer' ? 'selected' : ''}>Short answer</option></select></div></div><div class="media-strip"><button class="media-button" data-media>${svg.image} Image</button><button class="media-button" data-media>${svg.audio} Audio</button><button class="media-button" data-media>${svg.video} Video URL</button></div><div class="answers-head"><span>${q.type === 'Short answer' ? 'Answer key' : 'Answer choices'}</span><span>${q.type === 'Multiple choice' ? 'Correct choices add points; wrong choices subtract' : 'Select the correct answer'}</span></div><div id="answer-list">${answerRows(q)}</div>${q.type !== 'Short answer' ? `<button class="button ghost small" data-add-option>${svg.plus} Add answer</button>` : ''}<div class="explanation-box field"><label for="explanation">Explanation shown with feedback</label><textarea id="explanation" placeholder="Explain why the answer is correct">${q.explanation}</textarea></div></div></div></section>
      <aside class="settings-rail"><div class="rail-heading"><strong>Quiz settings</strong></div><div class="settings-content"><div class="setting-group"><h3>Timing</h3><div class="field"><label>Total quiz timer</label><div class="timer-inline"><input type="number" value="35" min="1"><select><option>minutes</option></select></div></div><div class="field"><label>This question</label><div class="timer-inline"><input type="number" value="${q.timer}" min="5"><select><option>seconds</option></select></div></div></div><div class="setting-group"><h3>Behavior</h3><label class="switch-row"><span class="switch-copy"><strong>Shuffle questions</strong><small>Each attempt gets a different order.</small></span><button class="switch on" type="button" aria-label="Shuffle questions"></button></label><label class="switch-row"><span class="switch-copy"><strong>Shuffle choices</strong><small>Randomize options for every student.</small></span><button class="switch on" type="button" aria-label="Shuffle choices"></button></label><label class="switch-row"><span class="switch-copy"><strong>Integrity monitoring</strong><small>Log tab switches, focus loss, and inactivity.</small></span><button class="switch on" type="button" aria-label="Integrity monitoring"></button></label></div><div class="setting-group"><h3>Results</h3><div class="field"><label>Show student results</label><select><option>At the end</option><option>After each question</option><option>Teacher only</option></select></div><div class="field"><label>Attempts</label><select><option>One per email</option><option>Unlimited</option></select></div></div></div></aside></div></div>`;
  view.querySelector('[data-go]').addEventListener('click', event => location.hash = event.currentTarget.dataset.go);
  view.querySelectorAll('[data-question]').forEach(button => button.addEventListener('click', () => { selectedQuestion = Number(button.dataset.question); renderBuilder(); }));
  view.querySelectorAll('.switch').forEach(button => button.addEventListener('click', () => button.classList.toggle('on')));
  view.querySelectorAll('[data-media]').forEach(button => button.addEventListener('click', () => showToast('Media picker opened — prototype interaction.')));
  view.querySelector('[data-save]').addEventListener('click', () => showToast('Draft saved.'));
  view.querySelector('[data-publish]').addEventListener('click', () => showToast('Quiz is ready to publish.'));
  view.querySelector('[data-preview]').addEventListener('click', () => location.hash = '#student-start');
  view.querySelector('[data-add-question]').addEventListener('click', () => { quizQuestions.push({ type: 'Single choice', title: 'Untitled question', points: 1, timer: 45, options: ['Option one', 'Option two'], correct: [0], explanation: '' }); selectedQuestion = quizQuestions.length - 1; renderBuilder(); showToast('Question added.'); });
  view.querySelectorAll('[data-correct]').forEach(button => button.addEventListener('click', () => { const index = Number(button.dataset.correct); if (q.type === 'Single choice') q.correct = [index]; else q.correct = q.correct.includes(index) ? q.correct.filter(i => i !== index) : [...q.correct, index]; renderBuilder(); }));
  view.querySelectorAll('[data-delete-option]').forEach(button => button.addEventListener('click', () => { if (q.options.length <= 2) return showToast('A choice question needs at least two answers.'); const index = Number(button.dataset.deleteOption); q.options.splice(index, 1); q.correct = q.correct.filter(i => i !== index).map(i => i > index ? i - 1 : i); renderBuilder(); }));
  const addOption = view.querySelector('[data-add-option]');
  if (addOption) addOption.addEventListener('click', () => { q.options.push('New answer'); renderBuilder(); });
  view.querySelector('#question-type').addEventListener('change', event => { q.type = event.target.value; if (q.type === 'Short answer') { q.options = ['accepted answer']; q.correct = [0]; } else if (q.options.length < 2) { q.options = ['Option one', 'Option two']; q.correct = [0]; } renderBuilder(); });
}

function renderResults() {
  setAdminHeader('IELTS Academic Reading', 'Results', 'results');
  const attempts = [['Sardor Abdullaev','sardor.a@mail.com','86%','27m 14s','1'],['Madina Nurmatova','madina.n@mail.com','82%','30m 02s','0'],['Jasur Olimov','jasur.o@mail.com','74%','34m 48s','3'],['Dilnoza Tursunova','dilnoza.t@mail.com','91%','25m 37s','0']];
  view.innerHTML = `<div class="section-heading"><div><h2>IELTS Academic Reading</h2><p>Published 10 September · 16 questions · 35 minute limit</p></div><div class="section-actions"><button class="button secondary" data-demo="pdf">${svg.download} Quiz PDF</button><button class="button primary" data-export>${svg.download} Export CSV</button></div></div><div class="result-metrics"><article class="result-summary"><div><p>Completed attempts</p><strong>128</strong><span class="trend">+18 this week</span></div><span class="metric-icon">${svg.users}</span></article><article class="result-summary"><div><p>Average score</p><strong>82%</strong><span class="trend">6% above workspace</span></div><span class="metric-icon blue">${svg.score}</span></article><article class="result-summary"><div><p>Average time</p><strong>29m 18s</strong><span class="trend">5m 42s remaining</span></div><span class="metric-icon amber">${svg.clock}</span></article></div>
    <div class="results-layout"><section class="panel distribution"><div><h2>Score distribution</h2><p class="hint">Most students scored between 70% and 89%.</p></div><div class="bar-chart">${[['0–49',12],['50–59',24],['60–69',42],['70–79',71],['80–89',100],['90–100',64]].map((item,i) => `<div class="bar-group"><div class="bar ${i === 4 ? 'accent' : ''}" style="height:${item[1]}%"></div><span>${item[0]}</span></div>`).join('')}</div></section><aside class="panel"><div class="panel-head"><h2>Quick insights</h2></div><div class="insight-list"><div class="insight"><span class="insight-icon">${svg.score}</span><span><strong>Question 11 needs review</strong><small>Only 34% answered correctly—the lowest in this quiz.</small></span></div><div class="insight"><span class="insight-icon">${svg.clock}</span><span><strong>Question 6 takes longest</strong><small>Students spend an average of 2 minutes 18 seconds.</small></span></div><div class="insight"><span class="insight-icon">${svg.shield}</span><span><strong>12 integrity flags</strong><small>Across 8 attempts. No automatic penalties applied.</small></span></div></div></aside></div>
    <section class="panel attempts-panel"><div class="panel-head"><h2>Student attempts</h2><label class="search-field">${svg.search}<input type="search" placeholder="Search students" aria-label="Search students"></label></div><div class="table-wrap"><table class="quiz-table"><thead><tr><th>Student</th><th>Score</th><th>Time</th><th>Integrity flags</th></tr></thead><tbody>${attempts.map(a => `<tr class="attempt-row" data-student="${a[0]}" data-email="${a[1]}" data-score="${a[2]}" data-time="${a[3]}" data-flags="${a[4]}"><td><span class="quiz-name"><strong>${a[0]}</strong><small>${a[1]}</small></span></td><td class="score">${a[2]}</td><td>${a[3]}</td><td class="${Number(a[4]) ? 'risk-count' : ''}">${a[4]}</td></tr>`).join('')}</tbody></table></div></section><div class="drawer-backdrop" data-close-drawer></div><aside class="result-drawer" aria-label="Attempt details"><div class="drawer-head"><h2>Attempt details</h2><button class="icon-button" data-close-drawer aria-label="Close">×</button></div><div id="drawer-content"></div></aside>`;
  view.querySelector('[data-export]').addEventListener('click', () => showToast('CSV export prepared — prototype.'));
  view.querySelector('[data-demo="pdf"]').addEventListener('click', () => showToast('Printable PDF is planned for Phase 2.'));
  view.querySelectorAll('.attempt-row').forEach(row => row.addEventListener('click', () => { document.querySelector('#drawer-content').innerHTML = `<p class="eyebrow">${row.dataset.email}</p><h3>${row.dataset.student}</h3><div class="big-score"><strong>${row.dataset.score}</strong><span>final score</span></div><div class="detail-list"><div class="detail-row"><span>Time used</span><strong>${row.dataset.time}</strong></div><div class="detail-row"><span>Correct answers</span><strong>13 of 16</strong></div><div class="detail-row"><span>Integrity flags</span><strong>${row.dataset.flags}</strong></div><div class="detail-row"><span>Submitted</span><strong>Today, 10:42</strong></div></div><button class="button secondary" style="width:100%;margin-top:18px">Review all answers</button>`; view.querySelector('.result-drawer').classList.add('open'); view.querySelector('.drawer-backdrop').classList.add('open'); }));
  view.querySelectorAll('[data-close-drawer]').forEach(el => el.addEventListener('click', () => { view.querySelector('.result-drawer').classList.remove('open'); view.querySelector('.drawer-backdrop').classList.remove('open'); }));
}

function studentHeader(note = 'Assessment by Aziza Karimova') { return `<header class="student-header"><a class="student-brand" href="#dashboard"><span class="brand-mark">A</span><span>Assessly</span></a><span class="student-header-note">${note}</span></header>`; }

function renderStudentStart() {
  document.body.classList.add('student-mode'); topbar.style.display = 'none';
  view.innerHTML = `<div class="student-shell">${studentHeader()}<main class="student-start"><section class="start-info"><p class="overline">IELTS preparation · Reading</p><h1>Academic Reading Practice</h1><p>Read each question carefully. Questions appear one at a time and cannot be revisited after you continue.</p><div class="quiz-facts"><div class="quiz-fact"><strong>16</strong><span>Questions</span></div><div class="quiz-fact"><strong>35 min</strong><span>Time limit</span></div><div class="quiz-fact"><strong>20 pts</strong><span>Total score</span></div></div></section><form class="start-form" id="start-form"><h2>Before you begin</h2><p>Enter your details exactly as your teacher knows them. Your progress will be saved in this browser.</p><div class="field"><label for="student-name">Full name</label><input id="student-name" required placeholder="e.g. Madina Nurmatova"></div><div class="field"><label for="student-email">Email address</label><input id="student-email" type="email" required placeholder="student@example.com"></div><div class="notice">${svg.alert}<span>Integrity monitoring is on. Leaving this tab, losing window focus, or extended inactivity will be recorded for your teacher.</span></div><button class="button primary" type="submit">Start assessment ${svg.chevron}</button><button class="button ghost" type="button" data-back style="margin-top:8px">Return to teacher view</button></form></main></div>`;
  view.querySelector('#start-form').addEventListener('submit', event => { event.preventDefault(); playerState.current = 0; playerState.remaining = 1800; playerState.answers = [[], [], '']; location.hash = '#student'; });
  view.querySelector('[data-back]').addEventListener('click', () => location.hash = '#builder');
}

const studentQuestions = [
  { type: 'Single choice', points: 2, prompt: 'What is the main purpose of the passage?', options: ['To explain a natural process', 'To compare two research methods', 'To describe a historical event', 'To argue for policy change'] },
  { type: 'Select all that apply', points: 3, prompt: 'Which two statements are supported by the speaker?', options: ['The trial lasted six months', 'Participants worked in teams', 'Results were published online', 'The control group was larger'], multi: true },
  { type: 'Short answer', points: 1, prompt: 'Complete the sentence with one word: Seasonal _____ helps the animals find food.', text: true }
];

function startPlayerTimer() { clearInterval(playerState.interval); playerState.interval = setInterval(() => { playerState.remaining = Math.max(0, playerState.remaining - 1); const timer = document.querySelector('#timer-value'); if (timer) timer.textContent = formatTime(playerState.remaining); if (playerState.remaining === 0) { clearInterval(playerState.interval); location.hash = '#complete'; } }, 1000); }
function formatTime(value) { return `${String(Math.floor(value / 60)).padStart(2, '0')}:${String(value % 60).padStart(2, '0')}`; }

function renderStudentQuestion() {
  document.body.classList.add('student-mode'); topbar.style.display = 'none';
  const q = studentQuestions[playerState.current]; const progress = ((playerState.current + 1) / studentQuestions.length) * 100; const saved = playerState.answers[playerState.current];
  const options = q.text ? `<input class="text-answer" id="text-answer" value="${typeof saved === 'string' ? saved : ''}" placeholder="Type one word" autocomplete="off">` : `<div class="student-options">${q.options.map((option, index) => `<button class="student-option ${Array.isArray(saved) && saved.includes(index) ? 'selected' : ''}" type="button" data-option="${index}"><span class="option-key">${String.fromCharCode(65 + index)}</span><span class="option-text">${option}</span></button>`).join('')}</div>`;
  view.innerHTML = `<div class="student-shell"><header class="player-header"><div class="player-title"><strong>Academic Reading Practice</strong><span>Aziza Karimova</span></div><div class="player-progress"><div class="progress-copy"><span>Question ${playerState.current + 1} of ${studentQuestions.length}</span><span>${Math.round(progress)}% complete</span></div><div class="progress-track"><div class="progress-fill" style="width:${progress}%"></div></div></div><div class="timer">${svg.clock}<span id="timer-value">${formatTime(playerState.remaining)}</span></div></header><main class="player-main"><section class="question-card"><div class="question-meta"><span>${q.type}</span><span>·</span><span>${q.points} points</span></div><h1>${q.prompt}</h1>${options}<p class="question-help">${q.multi ? 'Choose every answer you believe is correct. Incorrect selections reduce partial credit.' : 'Your answer is saved automatically.'}</p></section></main><footer class="player-footer"><button class="button primary" id="next-question">${playerState.current === studentQuestions.length - 1 ? 'Submit assessment' : 'Save & continue'} ${svg.chevron}</button></footer></div>`;
  if (q.text) view.querySelector('#text-answer').addEventListener('input', event => playerState.answers[playerState.current] = event.target.value);
  else view.querySelectorAll('[data-option]').forEach(button => button.addEventListener('click', () => { const index = Number(button.dataset.option); let current = Array.isArray(playerState.answers[playerState.current]) ? playerState.answers[playerState.current] : []; if (q.multi) current = current.includes(index) ? current.filter(i => i !== index) : [...current, index]; else current = [index]; playerState.answers[playerState.current] = current; view.querySelectorAll('[data-option]').forEach(option => option.classList.toggle('selected', current.includes(Number(option.dataset.option)))); }));
  view.querySelector('#next-question').addEventListener('click', () => { const answer = playerState.answers[playerState.current]; if ((!q.text && (!Array.isArray(answer) || answer.length === 0)) || (q.text && !String(answer).trim())) return showToast('Choose or enter an answer before continuing.'); if (playerState.current < studentQuestions.length - 1) { playerState.current += 1; renderStudentQuestion(); } else location.hash = '#complete'; });
  startPlayerTimer();
}

function renderCompletion() {
  clearInterval(playerState.interval); document.body.classList.add('student-mode'); topbar.style.display = 'none';
  view.innerHTML = `<div class="student-shell">${studentHeader('Assessment submitted')}<main class="completion"><div class="result-ring"><span>82%</span></div><p class="eyebrow">Submission complete</p><h1>Well done, Madina.</h1><p>Your answers have been saved and shared with your teacher.</p><div class="completion-grid"><div class="completion-stat"><span>Score</span><strong>16.4 / 20</strong></div><div class="completion-stat"><span>Correct</span><strong>13 of 16</strong></div><div class="completion-stat"><span>Time used</span><strong>29m 18s</strong></div></div><div class="integrity-message">${svg.shield}<span>Assessment completed with no integrity warnings.</span></div><button class="button secondary" data-teacher>Return to prototype dashboard</button></main></div>`;
  view.querySelector('[data-teacher]').addEventListener('click', () => location.hash = '#dashboard');
}

function route() {
  const routeName = location.hash.replace('#', '') || 'dashboard'; clearInterval(playerState.interval); view.style.animation = 'none'; requestAnimationFrame(() => view.style.animation = '');
  if (routeName === 'quizzes') return renderQuizzes();
  if (routeName === 'builder') return renderBuilder();
  if (routeName === 'results') return renderResults();
  if (routeName === 'student-start') return renderStudentStart();
  if (routeName === 'student') return renderStudentQuestion();
  if (routeName === 'complete') return renderCompletion();
  renderDashboard();
}

window.addEventListener('hashchange', route);
route();

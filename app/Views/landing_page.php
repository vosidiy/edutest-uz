<?php

$isAuthenticated = (bool) ($authenticated ?? false);
$accountUrl       = $isAuthenticated ? site_url('dashboard') : site_url('register');
$accountLabel     = $isAuthenticated ? 'Ish maydoniga otish' : 'Test yaratish';
$landingCss       = FCPATH . 'css/landing.css';
$landingJs        = FCPATH . 'js/landing.js';
$introImage       = base_url('images/landing/intro.png');
$placeholderImage = base_url('images/landing/placeholder.jpg');
?>
<!doctype html>
<html lang="uz" data-theme="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>123test -  Oqituvchilar uchun test tayyorlash platformasi</title>
    <meta name="description" content="Test imtihoni yaratish programmasi. Oquvchilar bilimini baholang, anonim mashq tayyorlang va baholash natijalarini kuzating.">
    <meta name="theme-color" content="#4338ca">
    <meta property="og:type" content="website">
    <meta property="og:title" content="123test dasturi  Oqituvchilar uchun test yaratish platformasi">
    <meta property="og:description" content="123 test bilan test yarating va ulashing. Oquvchilar bilimini baholang, anonim mashq tayyorlang va baholash natijalarini kuzating.">
    <meta property="og:site_name" content="123test">
    <meta property="og:locale" content="uz_UZ">
    <meta name="twitter:card" content="summary">
    <meta name="twitter:title" content="123 test  Oqituvchilar uchun test yaratish platformasi">
    <meta name="twitter:description" content="123test bilan test yarating va ulashing. Oquvchilar bilimini baholang, anonim mashq tayyorlang va baholash natijalarini kuzating.">
    <link rel="icon" href="/favicon.ico" sizes="any">
    <link rel="preload" href="<?= esc(base_url('fonts/inter/Inter-Regular.woff2'), 'attr') ?>" as="font" type="font/woff2" crossorigin>
    <link rel="preload" href="<?= esc(base_url('fonts/inter/Inter-SemiBold.woff2'), 'attr') ?>" as="font" type="font/woff2" crossorigin>
    <link rel="stylesheet" href="<?= esc(base_url('css/landing.css') . '?v=' . filemtime($landingCss), 'attr') ?>">
    <script type="application/ld+json">
    {
        "@context": "https://schema.org",
        "@type": "WebApplication",
        "name": "123test",
        "description": "123test bilan test yarating va ulashing. Oquvchilar bilimini baholang, anonim mashq tayyorlang va baholash natijalarini kuzating.",
        "applicationCategory": "EducationalApplication",
        "inLanguage": "uz",
        "operatingSystem": "Web browser"
    }
    </script>
</head>
<body id="header">
<a class="skip-link" href="#main-content">Asosiy mazmunga otish</a>

<header class="site-header">
    <div class="container header-inner">
        <a class="brand" href="#header" aria-label="123test bosh sahifasi">
            <svg class="brand-mark" aria-hidden="true" focusable="false" viewBox="0 0 36 36" xmlns="http://www.w3.org/2000/svg">
                <defs>
                    <linearGradient id="brand-gradient" x1="18" x2="34" y1="0" y2="36" gradientUnits="userSpaceOnUse">
                        <stop stop-color="#a5b4fc"/>
                        <stop offset="1" stop-color="#6163fe"/>
                    </linearGradient>
                </defs>
                <rect width="36" height="36" rx="12" fill="url(#brand-gradient)"/>
                <rect x="7" y="7" width="10" height="10" rx="4" fill="#4338ca"/>
                <rect x="19" y="7" width="10" height="10" rx="4" fill="#4338ca"/>
                <rect x="7" y="19" width="10" height="10" rx="4" fill="#4338ca"/>
                <rect x="19" y="19" width="10" height="10" rx="4" fill="#4338ca"/>
            </svg>
            <span style="font-weight:semibold">123<em style="padding-left:4px">test</em></span>
        </a>

        <nav class="primary-nav" id="landing-menu" aria-label="Asosiy menyu">
            <a href="#header">Bosh sahifa</a>
            <a href="#features">Imkoniyatlar</a>
            <a href="#faq">Savol-javob</a>
            <button class="nav-button" type="button" data-dialog-open="audience-dialog">Kimlar uchun?</button>
            <a class="mobile-account-link" href="<?= esc($accountUrl, 'attr') ?>"><?= esc($accountLabel) ?></a>
        </nav>

        <div class="header-actions">
            <?php if ($isAuthenticated) : ?>
                <a class="button button-primary" href="<?= esc(site_url('dashboard'), 'attr') ?>">Ish maydoniga otish</a>
            <?php else : ?>
                <a class="button button-secondary header-create" href="<?= esc(site_url('register'), 'attr') ?>">Test yaratish</a>
                <a class="button button-primary" href="<?= esc(site_url('login'), 'attr') ?>">Hisobga kirish</a>
            <?php endif ?>
            <button class="menu-toggle" type="button" aria-controls="landing-menu" aria-expanded="false" aria-label="Menyuni ochish">
                <svg aria-hidden="true" focusable="false" viewBox="0 0 24 24">
                    <path d="M4 5h16M4 12h16M4 19h16"/>
                </svg>
            </button>
        </div>
    </div>
</header>

<main id="main-content">
    <section class="hero section-hero" aria-labelledby="hero-title">
        <div class="container hero-panel">
            <div class="hero-copy">
                <p class="eyebrow">Test yaratish dasturi</p>
                <h1 id="hero-title">Test yarating. Onlayn imtihon o'tkazing</h1>
                <p class="hero-lead">123test bilan <strong>test yarating, oquvchilarga ulashing va natijalarni kuzating</strong>. Darsdagi bilimni baholang yoki mustaqil mashq uchun savollar tayyorlang  barchasi bitta platformada.</p>
                <div class="button-group">
                    <a class="button button-primary button-large" href="<?= esc($accountUrl, 'attr') ?>"><?= esc($accountLabel) ?></a>
                    <a class="button button-secondary button-large" href="#how-it-works">Qanday ishlaydi?</a>
                </div>
            </div>
            <div class="hero-media">
                <img src="<?= esc($introImage, 'attr') ?>" width="1374" height="961" alt="Test muharriri va oquvchi savol oynasi">
            </div>
        </div>
    </section>

    <section class="section" id="features" aria-labelledby="features-title">
        <div class="container">
            <header class="section-heading centered">
                <h2 id="features-title">Kimlar uchun?</h2>
                <p>123test ilovasidan har qanday ustozlar foydalanishi mumkin. Maktab, kollej, universitet, til (IELTS, TOEFL) markazlari...</p>
            </header>
            <div class="feature-grid">
                <article class="feature-card feature-red">
                    <div class="feature-image"><img src="<?= base_url('images/landing/feature-1.png'); ?>" width="480" height="200" alt="talim uchun test rasmi"></div>
                    <h3>O'quv dargohlari</h3>
                    <p>Davlat va xususuy maktablar, o'quv markazlari va boshqa talim dargohlari</p>
                </article>
                <article class="feature-card feature-purple">
                    <div class="feature-image"><img src="<?= base_url('images/landing/feature-2.png'); ?>" width="480" height="200" alt="Ustozlar uchun test yaratish"></div>
                    <h3>Yakka ustozlar</h3>
                    <p>Repetitor ustozlar, online va offline ustozlar, xorijiy til ustozlari</p>
                </article>
                <article class="feature-card feature-blue">
                    <div class="feature-image"><img src="<?= base_url('images/landing/feature-3.png'); ?>" width="480" height="320" alt="Xodimlar uchun test"></div>
                    <h3>Xodimlarni baholash</h3>
                    <p>Korxonalar o'z xodimlarning bilim va saviyasini baholash uchun</p>
                </article>
            </div>
        </div>
    </section>

    <div id="how-it-works">
        <section class="workflow section" aria-labelledby="build-title">
            <div class="container workflow-layout">
                <div class="workflow-media">
                    <img src="<?= base_url('images/landing/section-pic-builder.png'); ?>" width="600" height="480" alt="UI sample for quiz builder illustration">
                </div>
                <article class="workflow-copy">
                    <h2 id="build-title">Savollarni tayyorlang</h2>
                    <ul class="workflow-list">
                        <li><span aria-hidden="true">💻</span><p>Savollar va javob variantlarini yozing. Togri javoblarni belgilang va tushuntirish qoshing.</p></li>
                        <li><span aria-hidden="true">🎧</span><p>Savollarga rasm yoki audio qoshing. Video uchun YouTube havolasidan foydalaning.</p></li>
                        <li><span aria-hidden="true">✓</span><p>AI (Suniy Intelekt)  yordamida istalgan mavzu bo'yicha test savollari yarating</p></li>
                    </ul>
                    <a class="button button-primary button-large" href="<?= esc($accountUrl, 'attr') ?>"><?= esc($accountLabel) ?></a>
                </article>
            </div>
        </section>

        <section class="workflow section section-soft" aria-labelledby="results-title">
            <div class="container workflow-layout workflow-reverse">
                <div class="workflow-media">
                    <img src="<?= base_url('images/landing/section-pic-result.png'); ?>" width="600" height="480" alt="UI sample for quiz results">    
                </div>
                <article class="workflow-copy">
                    <h2 id="results-title">Natijalarni kuzating</h2>
                    <ul class="workflow-list">
                        <li><span aria-hidden="true">📊</span><p>Baholash natijalari va har bir ishtirokchining javoblarini bitta ish maydonida korib chiqing.</p></li>
                        <li><span aria-hidden="true">🔍</span><p>Urinishlar va javoblarni tekshiring va yakunlangan baholash natijalarini yuklab oling.</p></li>
                        <li><span aria-hidden="true">⏱️</span><p>Batafsil statistikani oling. Har bir ishtirokchi sarflagan vaqt haqida bilib oling</p></li>
                    </ul>
                    <a class="button button-primary button-large" href="<?= esc($accountUrl, 'attr') ?>"><?= esc($accountLabel) ?></a>
                </article>
            </div>
        </section>
    </div>

    <section class="section-compact" aria-label="Oqituvchi ish jarayoni">
        <div class="container">
            <div class="workflow-story" style="background-image:url( <?= base_url('images/landing/bg-teacher.png') ; ?> )">
                <article class="caption">
                    <p>Imtihon o'tkazish bu dars berishning eng ajralmas qismidir.</p>
                    <p>Endi hammasi juda oson, talabalarni bilimini doimiy tekshiramiz va qayerda qiynalayotgani ma'lum... "Ko'chirmachilik" ham imkonsiz ekan. Juda foydali dastur.</p>
                </article>
            </div>
        </div>
    </section>

    <section class="section" aria-label="Qoshimcha imkoniyatlar">
        <div class="container">
        <header class="section-heading centered">
                <h2 id="features-title">Ko'plab imkoniyatlar</h2>
                <p>Dastur shunchaki imtihon test savollari yaratish emas, balki butun imtihonni nazorat qilish jarayonini ham o'z ichiga oladi</p>
        </header>
        <div class="compact-grid">
            <article class="compact-card" style="background:#e0e7ff">
                <div class="compact-icon" aria-hidden="true">⌖</div>
                <div><h3>Imtihon uchun link</h3><p>Testni umumiy havola yoki maxsus raqamli kod orqali ulashing.</p></div>
            </article>
            <article class="compact-card" style="background:#eed8f4">
                <div class="compact-icon" aria-hidden="true">▯</div>
                <div><h3>Brauzerda ishlaydi</h3><p>Telefon, planshet yoki kompyuter brauzerida javob bering.</p></div>
            </article>
            <article class="compact-card" style="background:#e1f5ff">
                <div class="compact-icon" aria-hidden="true">↖</div>
                <div><h3>Ochiq va yopiq test</h3><p>Test barcha uchun ochiq (public) yoki yopiq guruh uchun yaratish mumkin</p></div>
            </article>
            <article class="compact-card"  style="background:#f4e7d3">
                <div class="compact-icon" aria-hidden="true">◫</div>
                <div><h3>Vaqt cheklovi</h3><p>Vaqt chegarasi, savollar tartibi va fikr-mulohazani sozlang.</p></div>
            </article>
            <article class="compact-card" style="background:#ccf6d5">
                <div class="compact-icon" aria-hidden="true">◫</div>
                <div><h3>Offline rejim</h3><p>Imtihon payti internet uzilishlariga qaramay test dasturi ishlayveradi</p></div>
            </article>
            <article class="compact-card" style="background:#ffe6e6">
                <div class="compact-icon" aria-hidden="true">◎</div>
                <div><h3>Rasm va audio savol</h3><p>Savollarga rasm, audio va video fayllar qoshing. Masalan Youtube link</p></div>
            </article>
            <article class="compact-card" style="background:#fadfff">
                <div class="compact-icon" aria-hidden="true">⇩</div>
                <div><h3>Excel hisobotlar</h3><p>Yakunlangan baholash natijalarini CSV formatida oling.</p></div>
            </article>
            <article class="compact-card" style="background:#edf0c2">
                <div class="compact-icon" aria-hidden="true">◫</div>
                <div><h3>G'irromlikka qarshi</h3><p>'Anti-cheating' - ya'ni o'quvchi brauzerdan chiqsa ustoz xabardor bo'ladi</p></div>
            </article>
            <article class="compact-card"  style="background:#e3e3e3">
                <div class="compact-icon" aria-hidden="true">◫</div>
                <div><h3>Avtomatik aralash</h3><p>Savol va variantlar har bir ishtirokchi uchun aralashtirish imkoni mavjud</p></div>
            </article>
        </div>
        </div>
    </section>

    <section class="section" id="get-started" aria-labelledby="get-started-title">
        <div class="container cta-panel">
            <h2 id="get-started-title">Birinchi testingizni yarating va ulashing</h2>
            <div class="button-group cta-buttons-wrap centered-actions">
                <?php if ($isAuthenticated) : ?>
                    <a class="button button-primary button-large" href="<?= esc(site_url('dashboard'), 'attr') ?>">
                       Tizimga kirish
                    </a>
                <?php else : ?>
                    <div>
                        <p class="text-sm">Siz yangimi?</p>
                        <a class="button button-primary button-large" href="<?= esc(site_url('register'), 'attr') ?>">
                            Ro'yxatdan o'tish
                        </a>
                    </div>
                    <div>
                        <p  class="text-sm">Avval ro'yxatdan o'tilganmi?</p>
                        <a class="button button-secondary button-large" href="<?= esc(site_url('login'), 'attr') ?>">
                            Tizimga kirish
                        </a>
                    </div>
                <?php endif ?>
            </div>
           
        </div>
    </section>

    <section class="section faq" id="faq" aria-labelledby="faq-title">
        <div class="container faq-inner">
            <header class="section-heading centered">
                <h2 id="faq-title">Ko'p so'raladigan savollar</h2>
                <p>Qoshimcha savollar bolsa bog'laning <br> Email: finalui@yandex.com</p>
            </header>
            <div class="faq-list">
                <article><h3>123test nima?</h3><p>123test  oqituvchilar uchun test yaratish va ulashish platformasi. Savollarni tayyorlang, baholash yoki mashq rejimini tanlang va testni oquvchilarga yuboring. Baholash rejimida javoblar hamda natijalarni korib chiqishingiz mumkin.</p></article>
                <article><h3>123test kimlar uchun?</h3><p>Mustaqil oqituvchilar, oqituvchi hisobi orqali ishlaydigan oquv markazlari va nomzodlar bilimini tekshiradigan rekruterlar uchun. Oquvchilar testda qatnashish uchun hisob yaratishi shart emas.</p></article>
                <article><h3>Testni qanday yarataman va ulashaman?</h3><p>1. Oqituvchi hisobini yarating.<br>2. Savollarni yozing va test sozlamalarini belgilang.<br>3. Testni elon qilib, havola yoki kodini ulashing.<br>4. Baholash rejimida javoblar va natijalarni korib chiqing.</p></article>
                <article><h3>Internet uzilib qolsa nima boladi?</h3><p>Testni boshlash uchun internet kerak. Boshlangan baholashda aloqa uzilsa, javob berishni davom ettirish mumkin; kutilayotgan javoblar wi-fi aloqasi qaytganda qayta serverga yuboriladi. Rasmiy natija server tasdiqlagach aniqlanadi. Mashqdagi jarayon faqat joriy brauzer oynasida saqlanadi.</p>
                </article>
                <article>
                    <h3>Baholash va mashq malumotlari qanday saqlanadi?</h3>
                    <p>Baholashda oquvchining ismi, javoblari va natijasi saqlanadi; email va telefon oqituvchi sozlamalariga bogliq. javoblar va natijalar serverda saqlanishi mumkin, ammo bu ustozning ixtiyoriga bog'liq (ya'ni test sozlamalaridan boshqariladi).</p></article>
            </div>
        </div>
    </section>
</main>

<footer class="site-footer">
    <div class="container footer-inner">
        <p><strong>123test.uz &copy; <?= date('Y') ?></strong><br>Oqituvchilar uchun test yaratish, ulashish va bilimni baholash platformasi</p>
        <nav aria-label="Pastki menyu">
            <a href="/">Bosh sahifa</a>
            <a href="#faq">Savol-javob</a>
        </nav>
    </div>
</footer>

<dialog class="audience-dialog" id="audience-dialog" aria-labelledby="audience-dialog-title">
    <div class="dialog-header">
        <h2 id="audience-dialog-title">123test kimlar uchun?</h2>
        <button class="dialog-close" type="button" data-dialog-close aria-label="Oynani yopish">
            <svg aria-hidden="true" focusable="false" viewBox="0 0 24 24"><path d="M19 5 5 19M5 5l14 14"/></svg>
        </button>
    </div>
    <p>123test mustaqil oqituvchilar, oquv markazlarida ishlovchi pedagoglar va rekruterlar uchun moljallangan. Har bir oqituvchi oz testlarini shaxsiy hisobida boshqaradi.</p>
    <button class="button button-secondary dialog-action" type="button" data-dialog-close>Tushunarli</button>
</dialog>

<script src="<?= esc(base_url('js/landing.js') . '?v=' . filemtime($landingJs), 'attr') ?>" defer></script>
</body>
</html>

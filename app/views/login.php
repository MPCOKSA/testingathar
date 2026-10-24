<section class="auth-page">
    <div class="auth-aside">
        <a href="/" class="auth-back">العودة إلى الرئيسية <span aria-hidden="true">←</span></a>
        <div class="auth-aside-content">
            <span class="eyebrow eyebrow-light">مرحبًا بعودتك</span>
            <h1>رحلتك لا تزال<br><span>تُكتب.</span></h1>
            <p>ادخل إلى مساحتك الخاصة، وتابع بناء ملفك على مهل.</p>
        </div>
        <div class="auth-aside-foot">«لكل جهدٍ صادقٍ أثر»</div>
        <span class="auth-aside-orbit auth-orbit-a"></span>
        <span class="auth-aside-orbit auth-orbit-b"></span>
    </div>
    <div class="auth-main">
        <div class="auth-form-wrap">
            <div class="auth-heading">
                <span class="eyebrow">مساحتك بانتظارك</span>
                <h2>تسجيل الدخول</h2>
                <p>أدخل بيانات حسابك لمتابعة ملف الإنجاز.</p>
            </div>
            <?php if (!empty($errors)): ?>
                <div class="form-alert" role="alert">
                    <?php foreach ($errors as $error): ?><p><?= e($error) ?></p><?php endforeach; ?>
                </div>
            <?php endif; ?>
            <?php if (!empty($blockedUntil)): ?><div class="ban-countdown" data-ban-until="<?= e($blockedUntil) ?>" role="status"><strong>الحساب محظور مؤقتًا</strong><span data-ban-countdown>يُحسب الوقت المتبقي…</span></div><?php endif; ?>
            <form method="post" action="/login" class="form-stack">
                <?= csrf_field() ?>
                <label class="field">
                    <span>البريد الإلكتروني</span>
                    <input type="email" name="email" value="<?= e($old['email'] ?? '') ?>" required autocomplete="email" dir="ltr" placeholder="name@example.com">
                </label>
                <label class="field">
                    <span>كلمة المرور</span>
                    <input type="password" name="password" required autocomplete="current-password" placeholder="أدخل كلمة المرور">
                </label>
                <button class="button button-navy button-full button-large" type="submit">دخول إلى حسابي <span aria-hidden="true">←</span></button>
            </form>
            <p class="auth-switch auth-forgot-link"><a href="/forgot-password">نسيت كلمة المرور؟</a></p>
            <p class="auth-switch">ليس لديك حساب؟ <a href="/register">أنشئ حسابًا جديدًا</a></p>
            <p class="auth-privacy-note"><svg viewBox="0 0 20 20" fill="none" aria-hidden="true"><path d="M10 2.5 16 5v4.4c0 3.4-2.5 6.2-6 8.1-3.5-1.9-6-4.7-6-8.1V5l6-2.5Z" stroke="currentColor" stroke-width="1.3"/><path d="m7.5 10 1.6 1.6 3.4-3.4" stroke="currentColor" stroke-width="1.3" stroke-linecap="round"/></svg> بياناتك خاصة ولا تُشارك تلقائيًا.</p>
        </div>
    </div>
</section>

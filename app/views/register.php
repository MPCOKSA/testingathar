<?php $selectedRole = $old['role'] ?? (($_GET['role'] ?? '') === 'student' ? 'student' : 'teacher'); ?>
<section class="auth-page register-page">
    <div class="auth-aside">
        <a href="/" class="auth-back">العودة إلى الرئيسية <span aria-hidden="true">←</span></a>
        <div class="auth-aside-content">
            <span class="eyebrow eyebrow-light">مساحة جديدة، بداية جديدة</span>
            <h1>ابدأ من هنا.<br><span>واترك للأثر مساحته.</span></h1>
            <p>أنشئ حسابًا مجانيًا، ثم أضف إلى ملفك ما تريد الاحتفاظ به.</p>
        </div>
        <div class="auth-aside-foot">خطوة صغيرة اليوم، حكاية أكبر غدًا</div>
        <span class="auth-aside-orbit auth-orbit-a"></span>
        <span class="auth-aside-orbit auth-orbit-b"></span>
    </div>
    <div class="auth-main">
        <div class="auth-form-wrap">
            <div class="auth-heading">
                <span class="eyebrow">أنشئ مساحتك الخاصة</span>
                <h2>حساب جديد</h2>
                <p>ابدأ ببياناتك الأساسية. يمكنك تطوير ملفك لاحقًا.</p>
            </div>
            <?php if (!empty($errors)): ?>
                <div class="form-alert" role="alert">
                    <?php foreach ($errors as $error): ?><p><?= e($error) ?></p><?php endforeach; ?>
                </div>
            <?php endif; ?>
            <form method="post" action="/register" class="form-stack">
                <?= csrf_field() ?>
                <label class="field">
                    <span>الاسم</span>
                    <input type="text" name="full_name" value="<?= e($old['full_name'] ?? '') ?>" required minlength="2" maxlength="100" autocomplete="name" placeholder="كيف تحب أن يظهر اسمك؟">
                </label>
                <label class="field">
                    <span>البريد الإلكتروني</span>
                    <input type="email" name="email" value="<?= e($old['email'] ?? '') ?>" required autocomplete="email" dir="ltr" placeholder="name@example.com">
                </label>
                <fieldset class="role-field">
                    <legend>ما نوع الملف الذي تريد إنشاءه؟</legend>
                    <div class="role-options">
                        <label class="role-option <?= $selectedRole === 'teacher' ? 'is-selected' : '' ?>">
                            <input type="radio" name="role" value="teacher" <?= $selectedRole === 'teacher' ? 'checked' : '' ?>>
                            <span class="role-card-icon"><svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M4 5.5h16v13H4zM8 9h8M8 12.5h5M7 3v5M17 3v5" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"/></svg></span>
                            <span><strong>معلم</strong><small>ملف إنجاز مهني</small></span>
                        </label>
                        <label class="role-option <?= $selectedRole === 'student' ? 'is-selected' : '' ?>">
                            <input type="radio" name="role" value="student" <?= $selectedRole === 'student' ? 'checked' : '' ?>>
                            <span class="role-card-icon"><svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="m3 9 9-5 9 5-9 5-9-5Z" stroke="currentColor" stroke-width="1.4" stroke-linejoin="round"/><path d="M6.5 11v5.5c3.5 3 7.5 3 11 0V11M21 9v6" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"/></svg></span>
                            <span><strong>طالب</strong><small>ملف إنجاز دراسي</small></span>
                        </label>
                    </div>
                </fieldset>
                <label class="field">
                    <span>كلمة المرور <small>12 حرفًا على الأقل، وبحد أقصى 72 بايت</small></span>
                    <input type="password" name="password" required minlength="12" maxlength="72" autocomplete="new-password" placeholder="أنشئ كلمة مرور قوية">
                </label>
                <label class="policy-consent"><input type="checkbox" name="accept_policies" value="1" required><span>أوافق على <a href="/privacy-policy" target="_blank" rel="noopener noreferrer">سياسة الخصوصية</a> و<a href="/publishing-policy" target="_blank" rel="noopener noreferrer">سياسة النشر</a>.</span></label>
                <button class="button button-navy button-full button-large" type="submit">إنشاء حسابي <span aria-hidden="true">←</span></button>
            </form>
            <p class="auth-switch">لديك حساب؟ <a href="/login">سجّل الدخول</a></p>
            <p class="auth-privacy-note"><svg viewBox="0 0 20 20" fill="none" aria-hidden="true"><path d="M10 2.5 16 5v4.4c0 3.4-2.5 6.2-6 8.1-3.5-1.9-6-4.7-6-8.1V5l6-2.5Z" stroke="currentColor" stroke-width="1.3"/><path d="m7.5 10 1.6 1.6 3.4-3.4" stroke="currentColor" stroke-width="1.3" stroke-linecap="round"/></svg> لا تستخدم بيانات شخصية حقيقية في هذه النسخة التجريبية.</p>
        </div>
    </div>
</section>

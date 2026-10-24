<section class="auth-page recovery-page">
    <div class="auth-aside"><a href="/login" class="auth-back">العودة إلى تسجيل الدخول <span aria-hidden="true">←</span></a><div class="auth-aside-content"><span class="eyebrow eyebrow-light">لا تقلق</span><h1>خطوة صغيرة<br><span>وتعود.</span></h1><p>أرسل رمزًا مؤقتًا إلى بريد الحساب لتعيين كلمة مرور جديدة.</p></div><div class="auth-aside-foot">الرمز صالح لمدة 15 دقيقة</div></div>
    <div class="auth-main"><div class="auth-form-wrap"><div class="auth-heading"><span class="eyebrow">استعادة الحساب</span><h2>نسيت كلمة المرور؟</h2><p>أدخل البريد المرتبط بحسابك وسنرسل لك رمز تحقق إذا كان مسجلًا لدينا.</p></div>
        <?php if (!empty($notice)): ?><div class="recovery-notice" role="status"><?= e($notice) ?></div><?php endif; ?>
        <?php if (!empty($errors)): ?><div class="form-alert" role="alert"><?php foreach ($errors as $error): ?><p><?= e($error) ?></p><?php endforeach; ?></div><?php endif; ?>
        <?php if (!empty($sent)): ?><a class="button button-outline-navy button-full" href="<?= e($resetUrl ?? '/reset-password') ?>">أدخل رمز التحقق</a><?php endif; ?>
        <form method="post" action="/forgot-password" class="form-stack"><?= csrf_field() ?>
            <label class="field"><span>البريد الإلكتروني</span><input type="email" name="email" value="<?= e($email ?? '') ?>" autocomplete="email" required dir="ltr" placeholder="name@example.com"></label>
            <button class="button button-navy button-full button-large" type="submit">إرسال رمز التحقق <span aria-hidden="true">←</span></button>
        </form><p class="auth-switch">تذكّرت كلمة المرور؟ <a href="/login">سجّل الدخول</a></p>
    </div></div>
</section>

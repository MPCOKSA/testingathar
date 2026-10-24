<section class="auth-page recovery-page">
    <div class="auth-aside"><a href="/login" class="auth-back">العودة إلى تسجيل الدخول <span aria-hidden="true">←</span></a><div class="auth-aside-content"><span class="eyebrow eyebrow-light">استعادة آمنة</span><h1>حسابك يعود<br><span>إليك.</span></h1><p>أدخل الرمز الذي أرسلناه إلى بريدك، ثم اختر كلمة مرور جديدة.</p></div><div class="auth-aside-foot">خصوصيتك وأمانك أولوية</div></div>
    <div class="auth-main"><div class="auth-form-wrap"><div class="auth-heading"><span class="eyebrow">خطوة أخيرة</span><h2>إعادة تعيين كلمة المرور</h2><p>استخدم الرمز المكوّن من 6 أرقام الذي وصلك عبر البريد الإلكتروني.</p></div>
        <?php if (!empty($errors)): ?><div class="form-alert" role="alert"><?php foreach ($errors as $error): ?><p><?= e($error) ?></p><?php endforeach; ?></div><?php endif; ?>
        <form method="post" action="/reset-password" class="form-stack"><?= csrf_field() ?>
            <label class="field"><span>البريد الإلكتروني</span><input type="email" name="email" value="<?= e($email ?? '') ?>" autocomplete="email" required dir="ltr" placeholder="name@example.com"></label>
            <label class="field"><span>رمز التحقق</span><input class="verification-code" type="text" name="code" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" minlength="6" autocomplete="one-time-code" required placeholder="000000" dir="ltr"></label>
            <label class="field"><span>كلمة المرور الجديدة</span><input type="password" name="password" minlength="10" autocomplete="new-password" required placeholder="10 أحرف على الأقل"></label>
            <label class="field"><span>تأكيد كلمة المرور</span><input type="password" name="password_confirmation" minlength="10" autocomplete="new-password" required placeholder="أعد كتابة كلمة المرور"></label>
            <button class="button button-navy button-full button-large" type="submit">حفظ كلمة المرور الجديدة</button>
        </form><p class="auth-switch"><a href="/forgot-password">إرسال رمز جديد</a></p>
    </div></div>
</section>

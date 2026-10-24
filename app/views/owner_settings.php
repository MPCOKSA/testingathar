<section class="dashboard-card owner-settings-card">
    <div class="section-card-heading"><div><span class="card-label">إعدادات الموقع</span><h2>الصيانة والبريد الإلكتروني</h2><p>إعدادات الموقع العامة ورسائل استعادة كلمات المرور.</p></div><span class="owner-badge">إعدادات حساسة</span></div>
    <form method="post" action="/owner/settings" class="owner-site-settings-form"><?= csrf_field() ?>
        <div class="maintenance-control">
            <div><strong>وضع الصيانة</strong><small>أوقف صفحات الزوار مؤقتًا. يظل تسجيل دخول المالك ولوحة التحكم متاحين.</small></div>
            <label class="switch-control"><input type="hidden" name="maintenance_enabled" value="0"><input type="checkbox" name="maintenance_enabled" value="1" <?= ($siteSettings['maintenance_enabled']??'0')==='1'?'checked':'' ?>><span>مفعّل</span></label>
        </div>
        <label class="field"><span>رسالة الصيانة للزوار</span><textarea name="maintenance_message" rows="2" maxlength="300"><?= e($siteSettings['maintenance_message']??'') ?></textarea></label>
        <div class="owner-mail-grid">
            <label class="field"><span>طريقة الإرسال</span><select name="mail_transport"><option value="smtp" <?= ($siteSettings['mail_transport']??'smtp')==='smtp'?'selected':'' ?>>SMTP — موصى به</option><option value="php_mail" <?= ($siteSettings['mail_transport']??'smtp')==='php_mail'?'selected':'' ?>>PHP mail() — إذا كانت الاستضافة تدعمه</option></select></label>
            <label class="field"><span>بريد المرسل</span><input type="email" name="mail_from_email" value="<?= e($siteSettings['mail_from_email']??'') ?>" maxlength="190" placeholder="no-reply@example.com" dir="ltr"></label>
            <label class="field"><span>اسم المرسل</span><input type="text" name="mail_from_name" value="<?= e($siteSettings['mail_from_name']??'أثر') ?>" maxlength="100"></label>
            <label class="field"><span>مضيف SMTP</span><input type="text" name="mail_smtp_host" value="<?= e($siteSettings['mail_smtp_host']??'') ?>" maxlength="190" placeholder="smtp.example.com" dir="ltr"></label>
            <label class="field"><span>المنفذ</span><input type="number" name="mail_smtp_port" value="<?= e($siteSettings['mail_smtp_port']??'587') ?>" min="1" max="65535" dir="ltr"></label>
            <label class="field"><span>التشفير</span><select name="mail_smtp_encryption"><option value="tls" <?= ($siteSettings['mail_smtp_encryption']??'tls')==='tls'?'selected':'' ?>>STARTTLS — غالبًا 587</option><option value="ssl" <?= ($siteSettings['mail_smtp_encryption']??'')==='ssl'?'selected':'' ?>>SSL — غالبًا 465</option><option value="none" <?= ($siteSettings['mail_smtp_encryption']??'')==='none'?'selected':'' ?>>بدون تشفير — غير موصى به</option></select></label>
            <label class="field"><span>اسم مستخدم SMTP</span><input type="text" name="mail_smtp_username" value="<?= e($siteSettings['mail_smtp_username']??'') ?>" maxlength="190" autocomplete="off" dir="ltr"></label>
            <label class="field"><span>كلمة مرور SMTP</span><input type="password" name="mail_smtp_password" maxlength="500" autocomplete="new-password" placeholder="<?= !empty($mailPasswordConfigured)?'اتركه فارغًا للإبقاء على الحالية':'أدخل كلمة المرور أو App Password' ?>"></label>
        </div>
        <label class="toggle-field clear-mail-secret"><input type="checkbox" name="clear_mail_password" value="1"><span>مسح كلمة مرور SMTP المحفوظة</span></label>
        <p class="helper-note">بيانات SMTP تحفظ في قاعدة بيانات الموقع ولا تظهر مجددًا بعد الحفظ. استخدم كلمة مرور تطبيق (App Password) عند مزود البريد، وتأكد أن الاستضافة تسمح باتصالات SMTP الصادرة. عند تعذر SMTP جرّب PHP mail() إذا كانت InfinityFree تتيحه.</p>
        <div class="share-actions"><button class="button button-gold" type="submit">حفظ إعدادات الموقع</button><span class="owner-settings-status"><?= !empty($mailPasswordConfigured)?'بيانات SMTP محفوظة':'لم تُضبط كلمة مرور SMTP بعد' ?></span></div>
    </form>
    <form method="post" action="/owner/mail-test" class="mail-test-form">
        <?= csrf_field() ?>
        <p class="helper-note">بعد الحفظ، أرسل رسالة اختبار إلى بريد حساب المالك الحالي للتحقق من الاتصال الفعلي بخادم SMTP.</p>
        <button class="button button-outline-navy" type="submit">إرسال رسالة اختبار</button>
    </form>
</section>

<section class="error-page">
    <span class="error-symbol" aria-hidden="true">!</span>
    <span class="eyebrow">حدث أمر غير متوقع</span>
    <h1><?= e($errorTitle ?? 'تعذر فتح الصفحة') ?></h1>
    <p><?= e($errorMessage ?? 'أعد المحاولة بعد قليل.') ?></p>
    <a href="<?= e($homeLink ?? '/') ?>" class="button button-navy">العودة إلى المساحة</a>
</section>

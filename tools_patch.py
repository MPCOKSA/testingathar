from pathlib import Path

root = Path('/home/ubuntu/athar-work')
index = root/'index.php'
text = index.read_text()
old = """    if (preg_match('#^/p/([a-f0-9]{64})(?:/(quick|pdf))?$#', $path, $match)) {
"""
new = """    if (preg_match('#^/evidence/(\\d+)/preview$#', $path, $match)) {
        require_user($user);
        $item = $evidenceRepo->owned((int)$match[1], (int)$user['id']);
        if (!$item) route_error('الدليل غير موجود.', 404);
        $filePath = Storage::path((string)$item['stored_name']);
        if (!is_file($filePath) || !is_readable($filePath)) route_error('ملف الدليل غير موجود.', 404);
        header('Content-Type: '.(string)$item['mime_type']);
        header('Content-Disposition: inline; filename*=UTF-8\'\''.rawurlencode((string)$item['original_name']));
        header('Content-Length: '.(string)filesize($filePath));
        header('X-Content-Type-Options: nosniff');
        readfile($filePath); exit;
    }
    if (preg_match('#^/p/([a-f0-9]{64})/evidence/(\\d+)$#', $path, $match)) {
        $token=$match[1]; $item=$evidenceRepo->publicForShare((int)$match[2], $token);
        if (!$item) route_error('هذا الشاهد غير متاح للعرض العام.',404);
        $filePath=Storage::path((string)$item['stored_name']);
        if (!is_file($filePath) || !is_readable($filePath)) route_error('ملف الشاهد غير موجود.',404);
        header('Content-Type: '.(string)$item['mime_type']);
        header('Content-Disposition: inline; filename*=UTF-8\'\''.rawurlencode((string)$item['original_name']));
        header('Content-Length: '.(string)filesize($filePath));
        header('X-Content-Type-Options: nosniff');
        readfile($filePath); exit;
    }
    if (preg_match('#^/p/([a-f0-9]{64})/section/(\\d+)$#', $path, $match)) {
        $token=$match[1]; $portfolio=$portfolios->findPublic($token);
        if (!$portfolio) route_error('هذا الرابط غير متاح أو تم إيقافه.',404);
        $sectionId=(int)$match[2]; $section=null;
        foreach ($portfolio['sections'] as $candidate) if ((int)$candidate['id']===$sectionId) { $section=$candidate; break; }
        if (!$section) route_error('هذا القسم غير متاح للعرض العام.',404);
        $portfolios->incrementPublicViews((int)$portfolio['id']);
        if (!(int)$portfolio['show_public_name']) $portfolio['headline']='';
        render('section_view',['pageTitle'=>$section['title'],'currentUser'=>null,'portfolio'=>$portfolio,'section'=>$section,'token'=>$token]); exit;
    }
    if (preg_match('#^/p/([a-f0-9]{64})(?:/(quick|pdf))?$#', $path, $match)) {
"""
if old not in text: raise SystemExit('route anchor not found')
index.write_text(text.replace(old,new,1))

editor = root/'app/views/editor.php'
text = editor.read_text()
old = """<a href=\"/evidence/<?= (int)$item['id'] ?>/download\">تنزيل</a><form method=\"post\" action=\"/evidence/<?= (int)$item['id'] ?>/visibility\">"""
new = """<?php $previewable = str_starts_with($item['mime_type'],'image/') || $item['mime_type']==='application/pdf'; ?><?php if ($previewable): ?><button type=\"button\" class=\"text-button evidence-preview-trigger\" data-evidence-preview=\"/evidence/<?= (int)$item['id'] ?>/preview\" data-evidence-title=\"<?= e($item['title']) ?>\">استعراض</button><?php endif; ?><a href=\"/evidence/<?= (int)$item['id'] ?>/download\">تنزيل</a><form method=\"post\" action=\"/evidence/<?= (int)$item['id'] ?>/visibility\">"""
if old not in text: raise SystemExit('editor evidence anchor not found')
editor.write_text(text.replace(old,new,1))

public = root/'app/views/public_portfolio.php'
text = public.read_text()
text = text.replace("<a href=\"#section-<?= (int)$section['id'] ?>\"><span><?= str_pad((string)($index+1),2,'0',STR_PAD_LEFT) ?></span><?= e($section['title']) ?></a>", "<a href=\"/p/<?= e($token) ?>/section/<?= (int)$section['id'] ?>\"><span><?= str_pad((string)($index+1),2,'0',STR_PAD_LEFT) ?></span><?= e($section['title']) ?></a>")
text = text.replace("<a href=\"#section-<?= (int)$section['id'] ?>\"><span><?= str_pad((string)($index+1),2,'0',STR_PAD_LEFT) ?></span><strong><?= e($section['title']) ?></strong><i>↗</i></a>", "<a href=\"/p/<?= e($token) ?>/section/<?= (int)$section['id'] ?>\"><span><?= str_pad((string)($index+1),2,'0',STR_PAD_LEFT) ?></span><strong><?= e($section['title']) ?></strong><i>↗</i></a>")
old = """<div><strong><?= e($item['title']) ?></strong><?php if (!empty($item['description'])): ?><p><?= e($item['description']) ?></p><?php endif; ?>
</div><span class=\"public-evidence-note\">دليل مختار</span>"""
new = """<div><strong><?= e($item['title']) ?></strong><?php if (!empty($item['description'])): ?><p><?= e($item['description']) ?></p><?php endif; ?>
</div><?php $publicPreviewable = str_starts_with($item['mime_type'],'image/') || $item['mime_type']==='application/pdf'; ?><?php if ($publicPreviewable): ?><a class=\"public-evidence-preview\" target=\"_blank\" rel=\"noopener\" href=\"/p/<?= e($token) ?>/evidence/<?= (int)$item['id'] ?>\">استعراض</a><?php endif; ?><span class=\"public-evidence-note\">دليل مختار</span>"""
if old not in text: raise SystemExit('public evidence anchor not found')
public.write_text(text.replace(old,new,1))

printview = root/'app/views/print.php'
text = printview.read_text()
text = text.replace('<section class="print-section"><div class="print-section-heading">', '<section class="print-section section-page"><div class="print-section-heading">')
printview.write_text(text)
PY
python3 /home/ubuntu/athar-work/tools_patch.py
rm /home/ubuntu/athar-work/tools_patch.py
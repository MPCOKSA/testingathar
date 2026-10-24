<?php
declare(strict_types=1);

function e(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** عرض التواريخ المخزنة بصيغة UTC بتوقيت الرياض (UTC+3)، مع الحفاظ على اللحظة الأصلية. */
function saudi_datetime(mixed $value): string
{
    if (!is_scalar($value)) return '';
    $raw=trim((string)$value);
    if ($raw==='') return '';
    try {
        $hasZone=(bool)preg_match('/(?:Z|[+-]\d{2}:?\d{2})$/i',$raw);
        $utc=new DateTimeZone('UTC');
        if ($hasZone) $date=new DateTimeImmutable($raw);
        else {
            $date=DateTimeImmutable::createFromFormat('!Y-m-d H:i:s',$raw,$utc);
            if (!$date) $date=new DateTimeImmutable($raw,$utc);
        }
        return $date->setTimezone(new DateTimeZone('Asia/Riyadh'))->format('Y-m-d H:i');
    } catch (Throwable) {
        return $raw;
    }
}

function redirect(string $path, int $status = 303): never
{
    header('Location: ' . $path, true, $status);
    exit;
}

function csrf_token(): string
{
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
}

function same_origin_request(): bool
{
    $source=(string)($_SERVER['HTTP_ORIGIN']??'');
    if ($source==='') $source=(string)($_SERVER['HTTP_REFERER']??'');
    if ($source==='') return true; // يظل رمز CSRF العشوائي إلزاميًا.
    $parts=parse_url($source); $hostHeader=(string)($_SERVER['HTTP_HOST']??''); $host=parse_url('http://'.$hostHeader);
    if (!is_array($parts)||!is_array($host)||empty($parts['scheme'])||empty($parts['host'])||empty($host['host'])) return false;
    $forwarded=strtolower(trim(explode(',',(string)($_SERVER['HTTP_X_FORWARDED_PROTO']??''))[0]));
    $secure=(!empty($_SERVER['HTTPS'])&&$_SERVER['HTTPS']!=='off')||$forwarded==='https';
    $sourceScheme=strtolower((string)$parts['scheme']);
    if (!in_array($sourceScheme,['http','https'],true)||($secure&&$sourceScheme!=='https')) return false;
    $sourcePort=(int)($parts['port']??($sourceScheme==='https'?443:80));
    $expectedPort=(int)($host['port']??($secure?443:80));
    return hash_equals(strtolower((string)$host['host']),strtolower((string)$parts['host'])) && $sourcePort===$expectedPort;
}

function verify_csrf(): bool
{
    $sent = $_POST['csrf_token'] ?? '';
    return same_origin_request() && is_string($sent)
        && isset($_SESSION['csrf_token'])
        && hash_equals($_SESSION['csrf_token'], $sent);
}

function valid_password(string $password): bool
{
    return mb_strlen($password)>=12 && strlen($password)<=72 && !preg_match('/[\x00-\x1F\x7F]/',$password);
}

function flash(string $type, string $message): void
{
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

function pull_flash(): ?array
{
    $message = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);
    return is_array($message) ? $message : null;
}

function audit_event(PDO $db, int $userId, int $portfolioId, string $type, string $description = ''): void
{
    $stmt = $db->prepare('INSERT INTO activity_log (user_id, portfolio_id, event_type, description) VALUES (:user, :portfolio, :type, :description)');
    $stmt->execute(['user'=>$userId, 'portfolio'=>$portfolioId, 'type'=>$type, 'description'=>mb_substr($description,0,180)]);
}

/** رسومات تعليمية ثابتة ومحلية؛ لا تحتوي مدخلات المستخدم على أي SVG خام. */
function subject_scene_svg(string $key, string $title = ''): string
{
    $theme=subject_theme($key,$title);
    $gradientId='subject-scene-'.bin2hex(random_bytes(5));
    $scene=match ($theme['class']) {
        'theme-biology' => '<circle class="scene-cell-membrane" cx="80" cy="80" r="52"/><circle class="scene-cell-inner" cx="80" cy="80" r="42"/><ellipse class="scene-organelle scene-organelle-a" cx="56" cy="59" rx="10" ry="5"/><ellipse class="scene-organelle scene-organelle-b" cx="105" cy="101" rx="11" ry="5"/><circle class="scene-nucleus" cx="82" cy="78" r="17"/><circle class="scene-nucleolus" cx="87" cy="74" r="6"/><g class="scene-cell-dots"><circle cx="54" cy="91" r="2.8"/><circle cx="102" cy="55" r="2.5"/><circle cx="69" cy="111" r="2"/><circle cx="111" cy="83" r="2.6"/></g>',
        'theme-science' => '<g class="scene-science-flask"><path d="M69 37h22m-5 0v31l22 38c4 7-1 14-9 14H61c-8 0-13-7-9-14l22-38V37"/><path d="M62 91h36"/><circle cx="68" cy="99" r="4"/><circle cx="84" cy="88" r="3"/><circle cx="91" cy="103" r="2.5"/></g>',
        'theme-chemistry' => '<g class="scene-molecule-bonds"><path d="M49 78 76 53 105 68 111 101 78 112 49 78"/><path d="m76 53 4 59"/></g><g class="scene-molecule-atoms"><circle cx="49" cy="78" r="10"/><circle cx="76" cy="53" r="9"/><circle cx="105" cy="68" r="12"/><circle cx="111" cy="101" r="8"/><circle cx="78" cy="112" r="10"/></g>',
        'theme-math' => '<g class="scene-math-cube"><path d="m80 35 38 22v45l-38 22-38-22V57l38-22Z M42 57l38 23 38-23M80 80v44"/><path d="m58 45 38 22v44M102 45 64 67v44"/></g><circle class="scene-math-point" cx="118" cy="57" r="5"/>',
        'theme-environment' => '<g class="scene-leaf"><path d="M44 105C44 65 71 39 119 41c-1 47-26 74-66 72-5 0-8-3-9-8Z"/><path d="M48 111c18-24 37-42 64-61M70 88l-2-24m19 8 19-2m-39 29-19-1"/></g><circle class="scene-earth-dot" cx="48" cy="111" r="5"/>',
        'theme-islamic' => '<g class="scene-star"><path d="m80 34 12 27 29 2-22 19 7 28-26-15-26 15 7-28-22-19 29-2 12-27Z"/><path d="m80 47 8 22 23 2-17 15 5 22-19-12-19 12 5-22-17-15 23-2 8-22Z"/></g><circle class="scene-star-center" cx="80" cy="80" r="8"/>',
        'theme-quran' => '<g class="scene-book"><path d="M80 61c-15-15-34-18-51-11v55c18-6 36-2 51 12 15-14 33-18 51-12V50c-17-7-36-4-51 11Z"/><path d="M80 61v56M40 66c12-3 22 0 30 7m-30 9c12-3 22 0 30 7m50-23c-12-3-22 0-30 7m30 9c-12-3-22 0-30 7"/></g><path class="scene-book-ribbon" d="m74 116 6 8 6-8"/>',
        'theme-english' => '<g class="scene-letters"><circle cx="80" cy="80" r="43"/><text x="62" y="72">A</text><text x="92" y="105">B</text><text x="42" y="112">C</text></g>',
        'theme-arabic' => '<g class="scene-letters scene-letters-ar"><circle cx="80" cy="80" r="43"/><text x="80" y="89" text-anchor="middle" direction="rtl">ع</text><circle class="scene-letter-dot" cx="108" cy="55" r="4"/><circle class="scene-letter-dot" cx="54" cy="106" r="3"/></g>',
        'theme-physics' => '<g class="scene-atom"><ellipse cx="80" cy="80" rx="47" ry="17"/><ellipse cx="80" cy="80" rx="47" ry="17" transform="rotate(60 80 80)"/><ellipse cx="80" cy="80" rx="47" ry="17" transform="rotate(120 80 80)"/></g><circle class="scene-atom-nucleus" cx="80" cy="80" r="9"/><circle class="scene-electron" cx="126" cy="80" r="5"/>',
        'theme-computer' => '<g class="scene-computer"><rect x="43" y="44" width="74" height="60" rx="9"/><path d="M61 117h38m-19-13v13M56 61h26m-26 10h18m14-10h17m-17 10h17"/><path class="scene-circuit" d="M53 44V34h14m40 10V34H94M43 83H31v12h12m74-12h12v12h-12"/><circle cx="106" cy="87" r="4"/></g>',
        'theme-social' => '<g class="scene-globe"><circle cx="80" cy="80" r="42"/><path d="M38 80h84M80 38c-15 13-22 27-22 42s7 29 22 42m0-84c15 13 22 27 22 42s-7 29-22 42M45 59h70M45 101h70"/><path class="scene-map" d="m61 62 11-7 8 4-2 10 9 5-5 9-12 2-4 11-10-8 4-12-8-5 9-9Zm26 25 10-4 9 8-4 12-10-2-5-8Z"/></g>',
        default => '<g class="scene-general"><circle cx="80" cy="80" r="42"/><path d="M80 47v66M47 80h66M57 57l46 46m0-46-46 46"/><circle cx="80" cy="80" r="14"/></g><path class="scene-spark" d="m121 35 4 10 10 4-10 4-4 10-4-10-10-4 10-4 4-10Z"/>',
    };
    return '<svg class="subject-scene-svg" viewBox="0 0 160 160" aria-hidden="true" focusable="false"><defs><linearGradient id="'.$gradientId.'-gloss" x1=".15" y1=".08" x2=".85" y2=".92"><stop stop-color="white" stop-opacity=".9"/><stop offset="1" stop-color="currentColor" stop-opacity=".18"/></linearGradient><radialGradient id="'.$gradientId.'-core"><stop stop-color="white"/><stop offset="1" stop-color="currentColor"/></radialGradient></defs>'.str_replace(['#scene-gloss','#scene-core'],['#'.$gradientId.'-gloss','#'.$gradientId.'-core'],$scene).'</svg>';
}

function render(string $template, array $data = [], int $status = 200): void
{
    http_response_code($status);
    extract($data, EXTR_SKIP);
    $flashMessage = pull_flash();
    $templatePath = dirname(__DIR__) . '/app/views/' . $template . '.php';
    if (!is_file($templatePath)) {
        throw new RuntimeException('The requested page template could not be found.');
    }
    require dirname(__DIR__) . '/app/views/layout.php';
}

function teacher_subject_options(): array { return [
    'general'=>'تخصص عام','arabic'=>'لغة عربية','english'=>'لغة إنجليزية','math'=>'رياضيات','biology'=>'أحياء','science'=>'علوم','chemistry'=>'كيمياء','physics'=>'فيزياء','islamic'=>'دراسات إسلامية','quran'=>'قرآن وتفسير','social'=>'اجتماعيات','computer'=>'حاسب وتقنية',
]; }
function teacher_subject_key(string $value): string {
    $v=strtolower(trim($value));
    $aliases=['arabic'=>['arabic','عربي','اللغة العربية','لغة عربية'],'english'=>['english','انجليزي','إنجليزي','اللغة الإنجليزية','لغة إنجليزية'],'math'=>['math','رياضيات','الرياضيات'],'biology'=>['biology','أحياء','احياء'],'science'=>['science','علوم','العلوم','أحياء وعلوم'],'chemistry'=>['chemistry','كيمياء'],'physics'=>['physics','فيزياء'],'islamic'=>['islamic','إسلامية','اسلامية','دراسات إسلامية'],'quran'=>['quran','قرآن','قران','تفسير'],'social'=>['social','اجتماعيات','الدراسات الاجتماعية'],'computer'=>['computer','حاسب','حاسب وتقنية','تقنية','برمجة']];
    foreach($aliases as $key=>$terms) foreach($terms as $term) if($v===strtolower($term)) return $key;
    foreach($aliases as $key=>$terms) foreach($terms as $term) if(strlen($term)>=4 && strpos($v,strtolower($term))!==false) return $key;
    return 'general';
}
function subject_theme_options(): array { return [
'general'=>['name'=>'عام','short'=>'ملف عام','class'=>'theme-general','bg'=>'#f4f7fb','accent'=>'#2d6f91','icon'=>'✦'],
'chemistry'=>['name'=>'كيمياء','short'=>'مختبر الكيمياء','class'=>'theme-chemistry','bg'=>'#eef8f5','accent'=>'#16866f','icon'=>'⚗'],
'math'=>['name'=>'رياضيات','short'=>'مساحة الرياضيات','class'=>'theme-math','bg'=>'#f1f3ff','accent'=>'#5867c9','icon'=>'∑'],
'biology'=>['name'=>'أحياء','short'=>'عالم الأحياء','class'=>'theme-biology','bg'=>'#f2faed','accent'=>'#5b8b43','icon'=>'◌'],
'environment'=>['name'=>'علم البيئة','short'=>'البيئة والاستدامة','class'=>'theme-environment','bg'=>'#eef9f1','accent'=>'#3a8b61','icon'=>'⌁'],
'islamic'=>['name'=>'دراسات إسلامية','short'=>'الدراسات الإسلامية','class'=>'theme-islamic','bg'=>'#fbf5e9','accent'=>'#a87a28','icon'=>'۞'],
'quran'=>['name'=>'قرآن وتفسير','short'=>'القرآن والتفسير','class'=>'theme-quran','bg'=>'#f7f1e8','accent'=>'#8d6541','icon'=>'۝'],
'english'=>['name'=>'إنجليزي','short'=>'اللغة الإنجليزية','class'=>'theme-english','bg'=>'#eef5fb','accent'=>'#3c78a8','icon'=>'Aa'],
'arabic'=>['name'=>'لغة عربية','short'=>'اللغة العربية','class'=>'theme-arabic','bg'=>'#fbf1f1','accent'=>'#a05252','icon'=>'ع'],
'physics'=>['name'=>'فيزياء','short'=>'مختبر الفيزياء','class'=>'theme-physics','bg'=>'#f0f5fb','accent'=>'#356a9b','icon'=>'ϟ'],
'science'=>['name'=>'علوم','short'=>'استكشاف العلوم','class'=>'theme-science','bg'=>'#f2f4ff','accent'=>'#6170b5','icon'=>'⚗'],
'computer'=>['name'=>'حاسب وتقنية','short'=>'مختبر التقنية','class'=>'theme-computer','bg'=>'#eef4ff','accent'=>'#536dba','icon'=>'⌘'],
'social'=>['name'=>'اجتماعيات','short'=>'الدراسات الاجتماعية','class'=>'theme-social','bg'=>'#faf3e8','accent'=>'#a66a3d','icon'=>'⌂']]; }
function subject_theme(string $key, string $title = ""): array { $o=subject_theme_options(); if (!isset($o[$key]) || $key==='general') { $m=['chemistry'=>'كيمياء','math'=>'رياضيات|الرياضيات|حساب','biology'=>'أحياء|احياء','environment'=>'بيئة|البيئة','islamic'=>'دراسات إسلامية|اسلاميات|إسلاميات','quran'=>'قرآن|قران|تفسير','english'=>'إنجليزي|انجليزي|اللغة الإنجليزية','arabic'=>'لغة عربية|العربي','physics'=>'فيزياء','social'=>'اجتماعيات','computer'=>'حاسب|الحاسب|تقنية|برمجة','science'=>'علوم|مختبر']; foreach($m as $k=>$rx) if($title!=="" && preg_match('/'.$rx.'/u',$title)) return $o[$k]; } return $o[$key]??$o['general']; }

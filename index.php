<?php
declare(strict_types=1);

require __DIR__ . '/app/bootstrap.php';
require __DIR__ . '/app/Database.php';
require __DIR__ . '/app/Auth.php';
require __DIR__ . '/app/SubscriptionService.php';
require __DIR__ . '/app/SiteSettings.php';
require __DIR__ . '/app/Mailer.php';
require __DIR__ . '/app/PortfolioRepository.php';
require __DIR__ . '/app/EvidenceRepository.php';
require __DIR__ . '/app/Storage.php';
require __DIR__ . '/app/TemplateCatalog.php';
require __DIR__ . '/app/Assistant.php';
require __DIR__ . '/app/helpers.php';

function clean_value(mixed $value, int $max): string
{
    if (!is_string($value)) return '';
    $value = trim(str_replace("\0", '', $value));
    return mb_substr($value, 0, $max);
}

function evidence_source_from_url(mixed $value): array
{
    $url=trim(is_string($value)?$value:'');
    if ($url==='') return ['url'=>'','kind'=>''];
    if (mb_strlen($url)>2000 || !filter_var($url,FILTER_VALIDATE_URL)) throw new RuntimeException('أدخل رابطًا صحيحًا يبدأ بـ https:// أو http://.');
    $parts=parse_url($url); $scheme=strtolower((string)($parts['scheme']??''));
    if (!in_array($scheme,['http','https'],true) || empty($parts['host'])) throw new RuntimeException('يسمح بروابط الويب وGoogle Drive فقط.');
    $host=strtolower((string)$parts['host']);
    $isDrive=str_contains($host,'drive.google.com') || str_contains($host,'docs.google.com') || str_contains($host,'docs.googleusercontent.com');
    return ['url'=>$url,'kind'=>$isDrive?'drive':'url'];
}

function route_error(string $message, int $status = 400): never
{
    render('error', ['pageTitle'=>'تعذر إكمال الطلب','currentUser'=>$GLOBALS['user'] ?? null,'errorTitle'=>'تعذر إكمال الطلب','errorMessage'=>$message,'homeLink'=>isset($GLOBALS['user']) && $GLOBALS['user'] ? '/dashboard' : '/'], $status);
    exit;
}

function require_user(?array $user): void
{
    if (!$user) redirect('/login', 302);
}

function require_owner(?array $user): void
{
    require_user($user);
    if (!(int)($user['is_owner'] ?? 0)) route_error('هذه الصفحة مخصصة لمالك الموقع فقط.', 403);
}

function require_subscription_feature(PDO $db,int $userId,string $feature): void
{
    if (!SubscriptionService::allows($db,$userId,$feature)) route_error('هذه الميزة غير متاحة في خطتك الحالية. تواصل مع مالك الموقع لترقية الاشتراك.',402);
}

function notify_owner_by_email(PDO $db, array $sender, string $subject, string $message): bool
{
    $recipient=(string)$db->query("SELECT email FROM users WHERE is_owner=1 AND is_active=1 ORDER BY id LIMIT 1")->fetchColumn();
    if (!filter_var($recipient,FILTER_VALIDATE_EMAIL)) return false;
    try {
        return Mailer::send($db,$recipient,$subject,$message,Mailer::brandedHtml($subject,'وصلت رسالة جديدة من '.((string)($sender['full_name']??'مستخدم أثر')).'.',$message,'يمكنك مراجعة الرسالة والرد عليها من لوحة المالك.'));
    } catch (Throwable $mailError) { error_log('[Athar owner notification] '.$mailError->getMessage()); return false; }
}

function send_download(string $path, string $mime, string $name, bool $deleteAfter = false): never
{
    if (!is_file($path) || !is_readable($path)) route_error('الملف غير موجود.', 404);
    $ascii = preg_replace('/[^A-Za-z0-9._-]/', '_', $name) ?: 'file';
    header('Content-Type: ' . $mime);
    header("Content-Disposition: attachment; filename=\"{$ascii}\"; filename*=UTF-8''" . rawurlencode($name));
    header('Content-Length: ' . (string)filesize($path));
    header('X-Content-Type-Options: nosniff');
    readfile($path);
    if ($deleteAfter) @unlink($path);
    exit;
}

try {
    $db = Database::connection();
    $GLOBALS['db'] = $db;
    $auth = new Auth($db);
    $portfolios = new PortfolioRepository($db);
    $evidenceRepo = new EvidenceRepository($db);
    $sessionUserId=(int)($_SESSION['user_id']??0); $sessionBan=null;
    if($sessionUserId){$banLookup=$db->prepare("SELECT reason,banned_until FROM user_bans WHERE user_id=:id AND (banned_until IS NULL OR banned_until>CURRENT_TIMESTAMP) LIMIT 1");$banLookup->execute(['id'=>$sessionUserId]);$sessionBan=$banLookup->fetch()?:null;}
    $user = $auth->user();
    if ($user) $db->prepare('UPDATE users SET last_seen_at=CURRENT_TIMESTAMP WHERE id=:id')->execute(['id'=>$user['id']]);
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
    if ($method === 'GET' && !($user && (int)($user['is_owner']??0)===1) && !preg_match('#^/(?:profile/avatar|p/[^/]+/(?:avatar|evidence/\d+))$#', $path)) {
        try { $visit=$db->prepare('INSERT INTO visit_log(user_id,visitor_ip,route,user_agent,is_authenticated) VALUES(:user,:ip,:route,:agent,:auth)'); $visit->execute(['user'=>$user?(int)$user['id']:null,'ip'=>Security::clientIp(),'route'=>mb_substr($path,0,240),'agent'=>mb_substr((string)($_SERVER['HTTP_USER_AGENT']??''),0,500),'auth'=>$user?1:0]); } catch (Throwable $visitError) { error_log('[Athar visit log] '.$visitError->getMessage()); }
    }

    $maintenanceEnabled = SiteSettings::get($db, 'maintenance_enabled', '0') === '1';
    $isSiteOwner = (int)($user['is_owner'] ?? 0) === 1;
    if ($maintenanceEnabled && !$isSiteOwner && !in_array($path, ['/login', '/logout', '/forgot-password', '/reset-password'], true)) {
        render('maintenance', ['pageTitle'=>'أثر — تحت الصيانة', 'currentUser'=>null, 'maintenanceMessage'=>SiteSettings::get($db, 'maintenance_message')], 503);
        exit;
    }

    if ($method === 'POST') {
        if (!verify_csrf()) { Security::event($db,$user?(int)$user['id']:null,'csrf_failed',$path); route_error('انتهت صلاحية الطلب. أعد تحميل الصفحة وحاول مجددًا.', 419); }

        if ($path === '/owner/template/toggle' && $user) {
            require_owner($user);
            $templateId=clean_value($_POST['template_id']??'',100); $template=TemplateCatalog::find($templateId);
            if (!$template) { flash('error','القالب غير موجود.'); redirect('/owner/templates'); }
            $enabled=empty($_POST['disable'])?1:0; $note=clean_value($_POST['admin_note']??'',300);
            $stmt=$db->prepare('INSERT INTO template_controls(template_id,is_enabled,admin_note,updated_by,updated_at) VALUES(:id,:enabled,:note,:owner,CURRENT_TIMESTAMP) ON CONFLICT(template_id) DO UPDATE SET is_enabled=excluded.is_enabled,admin_note=excluded.admin_note,updated_by=excluded.updated_by,updated_at=CURRENT_TIMESTAMP');
            $stmt->execute(['id'=>$templateId,'enabled'=>$enabled,'note'=>$note,'owner'=>$user['id']]); Security::event($db,(int)$user['id'],'template_control_changed','/owner/template/toggle',$templateId.'='.($enabled?'enabled':'disabled')); flash('success',$enabled?'تم تفعيل القالب.':'تم إيقاف استخدام القالب.'); redirect('/owner/templates');
        }
        if ($path === '/owner/update/save' && $user) {
            require_owner($user);
            $title=clean_value($_POST['title']??'',120); $version=clean_value($_POST['version']??'',40); $message=clean_value($_POST['message']??'',1200);
            $kind=in_array($_POST['kind']??'release',['release','notice','maintenance'],true)?$_POST['kind']:'release';
            if ($title==='' || $message==='') { flash('error','اكتب عنوان التحديث ووصفه.'); redirect('/owner/operations'); }
            $stmt=$db->prepare('INSERT INTO site_updates(title,version,message,kind,is_published,created_by) VALUES(:title,:version,:message,:kind,:published,:owner)');
            $stmt->execute(['title'=>$title,'version'=>$version,'message'=>$message,'kind'=>$kind,'published'=>empty($_POST['is_published'])?0:1,'owner'=>$user['id']]);
            Security::event($db,(int)$user['id'],'site_update_published','/owner/update/save',$version); flash('success','تم حفظ التحديث وسيظهر للمستخدمين.'); redirect('/owner/operations');
        }
        if ($path === '/owner/service/save' && $user) {
            require_owner($user);
            $key=clean_value($_POST['service_key']??'',40); $label=clean_value($_POST['label']??'',100); $note=clean_value($_POST['note']??'',300);
            $status=in_array($_POST['status']??'operational',['operational','maintenance','outage'],true)?$_POST['status']:'operational';
            if ($key==='' || $label==='') { flash('error','تحقق من اسم الخدمة ومعرّفها.'); redirect('/owner/operations'); }
            $stmt=$db->prepare('INSERT INTO service_statuses(service_key,label,status,note,updated_by,updated_at) VALUES(:key,:label,:status,:note,:owner,CURRENT_TIMESTAMP) ON CONFLICT(service_key) DO UPDATE SET label=excluded.label,status=excluded.status,note=excluded.note,updated_by=excluded.updated_by,updated_at=CURRENT_TIMESTAMP');
            $stmt->execute(['key'=>$key,'label'=>$label,'status'=>$status,'note'=>$note,'owner'=>$user['id']]);
            Security::event($db,(int)$user['id'],'service_status_saved','/owner/service/save',$key.'='.$status); flash('success','تم تحديث حالة الخدمة.'); redirect('/owner/operations');
        }
        if ($path === '/owner/plan/save' && $user) {
            require_owner($user);
            $id=(int)($_POST['plan_id']??0); $name=clean_value($_POST['name']??'',80); $description=clean_value($_POST['description']??'',500);
            $slug=strtolower(clean_value($_POST['slug']??'',48)); $slug=preg_replace('/[^a-z0-9-]/','-',$slug)??''; $slug=trim(preg_replace('/-+/','-',$slug)??'','-');
            if ($slug==='') $slug='plan-'.bin2hex(random_bytes(4));
            $priceInput=trim(is_string($_POST['price']??null)?$_POST['price']:'0');
            if ($name==='' || !preg_match('/^\d{1,8}(?:\.\d{1,2})?$/',$priceInput)) { flash('error','أدخل اسمًا وسعرًا صالحًا، مثل 0 أو 19.99.'); redirect('/owner'); }
            $priceParts=explode('.', $priceInput, 2); $priceMinor=((int)$priceParts[0])*100+(int)str_pad($priceParts[1]??'',2,'0');
            $currency=in_array($_POST['currency']??'SAR',['SAR','USD','EUR'],true)?$_POST['currency']:'SAR';
            $days=filter_var($_POST['duration_days']??30,FILTER_VALIDATE_INT,['options'=>['min_range'=>1,'max_range'=>3650]]);
            $evidence=filter_var($_POST['max_evidence']??24,FILTER_VALIDATE_INT,['options'=>['min_range'=>0,'max_range'=>10000]]);
            $sections=filter_var($_POST['max_sections']??24,FILTER_VALIDATE_INT,['options'=>['min_range'=>1,'max_range'=>100]]);
            if ($days===false||$evidence===false||$sections===false) { flash('error','تحقق من مدة الخطة وحدود الأقسام والأدلة.'); redirect('/owner'); }
            $values=['slug'=>$slug,'name'=>$name,'description'=>$description,'price'=>$priceMinor,'currency'=>$currency,'days'=>$days,'evidence'=>$evidence,'sections'=>$sections,'ai'=>empty($_POST['ai_enabled'])?0:1,'templates'=>empty($_POST['custom_templates_enabled'])?0:1,'sharing'=>empty($_POST['public_sharing_enabled'])?0:1,'uploads'=>empty($_POST['file_upload_enabled'])?0:1,'active'=>empty($_POST['is_active'])?0:1];
            if ($id>0) { $stmt=$db->prepare('UPDATE subscription_plans SET slug=:slug,name=:name,description=:description,price_minor=:price,currency=:currency,duration_days=:days,max_evidence=:evidence,max_sections=:sections,ai_enabled=:ai,custom_templates_enabled=:templates,public_sharing_enabled=:sharing,file_upload_enabled=:uploads,is_active=:active,updated_at=CURRENT_TIMESTAMP WHERE id=:id'); $values['id']=$id; $stmt->execute($values); }
            else { $stmt=$db->prepare('INSERT INTO subscription_plans(slug,name,description,price_minor,currency,duration_days,max_evidence,max_sections,ai_enabled,custom_templates_enabled,public_sharing_enabled,file_upload_enabled,is_active) VALUES(:slug,:name,:description,:price,:currency,:days,:evidence,:sections,:ai,:templates,:sharing,:uploads,:active)'); $stmt->execute($values); }
            Security::event($db,(int)$user['id'],'subscription_plan_saved','/owner/plan/save',$slug); flash('success','تم حفظ الخطة وميزاتها.'); redirect('/owner/plans');
        }

        if ($path === '/owner/subscription/set' && $user) {
            require_owner($user); $targetId=(int)($_POST['user_id']??0); $planId=(int)($_POST['plan_id']??0);
            $days=filter_var($_POST['duration_days']??null,FILTER_VALIDATE_INT,['options'=>['min_range'=>1,'max_range'=>3650]]);
            $planStmt=$db->prepare('SELECT id,duration_days FROM subscription_plans WHERE id=:id AND is_active=1'); $planStmt->execute(['id'=>$planId]); $plan=$planStmt->fetch();
            $targetStmt=$db->prepare('SELECT id,is_owner FROM users WHERE id=:id AND is_active=1'); $targetStmt->execute(['id'=>$targetId]); $target=$targetStmt->fetch();
            if (!$target||!$plan||$days===false) { flash('error','تحقق من المستخدم والخطة والمدة.'); redirect('/owner'); }
            if ((int)$target['is_owner']===1) { flash('error','لا يمكن تغيير اشتراك حساب مالك الموقع من هنا.'); redirect('/owner'); }
            $db->beginTransaction();
            try { $db->prepare("UPDATE user_subscriptions SET status='cancelled' WHERE user_id=:user AND status='active'")->execute(['user'=>$targetId]); $stmt=$db->prepare("INSERT INTO user_subscriptions(user_id,plan_id,status,starts_at,ends_at,admin_note,created_by) VALUES(:user,:plan,'active',CURRENT_TIMESTAMP,datetime('now','+'||:days||' days'),:note,:owner)"); $stmt->execute(['user'=>$targetId,'plan'=>$planId,'days'=>$days,'note'=>clean_value($_POST['admin_note']??'',500),'owner'=>$user['id']]); Security::invalidateUserSessions($db,$targetId); $db->commit(); }
            catch(Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}
            Security::event($db,(int)$user['id'],'subscription_assigned','/owner/subscription/set','user='.$targetId.' plan='.$planId); flash('success','تم تعيين الاشتراك وتحديد مدة صلاحيته.'); redirect('/owner/users');
        }

        if ($path === '/owner/policies' && $user) {
            require_owner($user); $db->beginTransaction();
            try { foreach(['privacy'=>'سياسة الخصوصية','publishing'=>'سياسة النشر'] as $key=>$defaultTitle) { $title=clean_value($_POST[$key.'_title']??$defaultTitle,120); $body=clean_value($_POST[$key.'_body']??'',20000); if(mb_strlen($body)<40) throw new RuntimeException('يجب أن تحتوي كل سياسة على 40 حرفًا على الأقل.'); $stmt=$db->prepare('UPDATE site_policies SET title=:title,body=:body,version=version+1,updated_at=CURRENT_TIMESTAMP WHERE policy_key=:key'); $stmt->execute(['title'=>$title,'body'=>$body,'key'=>$key]); } $db->commit(); }
            catch(Throwable $e){if($db->inTransaction())$db->rollBack();flash('error',$e instanceof RuntimeException?$e->getMessage():'تعذر حفظ السياسات.');redirect('/owner');}
            Security::event($db,(int)$user['id'],'policies_updated','/owner/policies'); flash('success','تم تحديث السياسات وإصدار نسخ جديدة.'); redirect('/owner/policies');
        }

        if ($path === '/owner/settings' && $user) {
            require_owner($user);
            $transport = clean_value($_POST['mail_transport'] ?? 'smtp', 20);
            $encryption = clean_value($_POST['mail_smtp_encryption'] ?? 'tls', 10);
            $host = clean_value($_POST['mail_smtp_host'] ?? '', 190);
            $port = filter_var($_POST['mail_smtp_port'] ?? 587, FILTER_VALIDATE_INT, ['options'=>['min_range'=>1,'max_range'=>65535]]);
            $fromEmail = trim(is_string($_POST['mail_from_email'] ?? null) ? $_POST['mail_from_email'] : '');
            $fromName = clean_value($_POST['mail_from_name'] ?? 'أثر', 100);
            if (!in_array($transport, ['smtp','php_mail'], true) || !in_array($encryption, ['tls','ssl','none'], true) || $port === false || ($fromEmail !== '' && !filter_var($fromEmail, FILTER_VALIDATE_EMAIL))) {
                flash('error', 'تحقق من طريقة الإرسال والتشفير والمنفذ وبريد المرسل.'); redirect('/owner');
            }
            $settings = [
                'maintenance_enabled'=>!empty($_POST['maintenance_enabled'])?'1':'0',
                'maintenance_message'=>clean_value($_POST['maintenance_message']??'',300),
                'mail_transport'=>$transport,
                'mail_smtp_host'=>$host,
                'mail_smtp_port'=>(string)$port,
                'mail_smtp_encryption'=>$encryption,
                'mail_smtp_username'=>clean_value($_POST['mail_smtp_username']??'',190),
                'mail_from_email'=>$fromEmail,
                'mail_from_name'=>$fromName !== '' ? $fromName : 'أثر',
            ];
            if (!empty($_POST['clear_mail_password'])) $settings['mail_smtp_password']='';
            elseif (is_string($_POST['mail_smtp_password']??null) && $_POST['mail_smtp_password']!=='') $settings['mail_smtp_password']=substr($_POST['mail_smtp_password'],0,500);
            SiteSettings::setMany($db, $settings);
            Security::event($db,(int)$user['id'],'owner_settings_saved','/owner/settings','maintenance=' . $settings['maintenance_enabled']);
            flash('success','تم حفظ إعدادات الموقع والبريد.'); redirect('/owner/settings');
        }

        if ($path === '/owner/mail-test' && $user) {
            require_owner($user);
            $recipient = filter_var((string)($user['email'] ?? ''), FILTER_VALIDATE_EMAIL) ? (string)$user['email'] : '';
            if ($recipient === '') { flash('error','لا يوجد بريد صالح لحساب المالك لاستقبال رسالة الاختبار.'); redirect('/owner/settings'); }
            try {
                $testBody="هذه رسالة اختبار من منصة أثر.\n\nإذا وصلتك هذه الرسالة فإعداد SMTP يعمل بصورة صحيحة.\n\nأثر";
                if (!Mailer::send($db, $recipient, 'اختبار بريد منصة أثر', $testBody, Mailer::brandedHtml('اختبار إعدادات البريد','إذا ظهرت هذه الرسالة في بريدك، فإن إعداد SMTP في منصة أثر يعمل بصورة صحيحة.','','تم إرسال هذه الرسالة بناءً على طلبك من لوحة المالك.'))) {
                    throw new RuntimeException('رفض مزود البريد إرسال رسالة الاختبار.');
                }
                Security::event($db,(int)$user['id'],'mail_test_sent','/owner/mail-test','recipient='.Security::fingerprint($recipient));
                flash('success','تم إرسال رسالة اختبار إلى بريد حساب المالك. تحقق من الوارد والرسائل غير المرغوب فيها.');
            } catch (Throwable $mailError) {
                error_log('[Athar mail test] '.get_class($mailError).': '.$mailError->getMessage());
                Security::event($db,(int)$user['id'],'mail_test_failed','/owner/mail-test');
                $mailReason=mb_strtolower($mailError->getMessage());
                $friendly=str_contains($mailReason,'كلمة مرور')?'لم تُدخل كلمة مرور SMTP أو App Password.':(str_contains($mailReason,'535')||str_contains($mailReason,'auth')||str_contains($mailReason,'بيانات الدخول')?'رفض Outlook بيانات الدخول؛ استخدم App Password صحيحة للحساب.':(str_contains($mailReason,'الاتصال')||str_contains($mailReason,'connect')?'تعذر الاتصال بخادم Outlook؛ تحقق من السماح باتصالات SMTP في الاستضافة.':'راجع إعدادات المضيف والمنفذ والتشفير وبيانات الدخول.'));
                flash('error','فشل اختبار البريد. '.$friendly);
            }
            redirect('/owner/settings');
        }

        if ($path === '/forgot-password' && !$user) {
            $email = mb_strtolower(trim(is_string($_POST['email']??null) ? $_POST['email'] : ''));
            $emailHash = Security::fingerprint($email);
            $ipHash = Security::fingerprint(Security::clientIp());
            $db->exec("DELETE FROM password_reset_requests WHERE created_at < datetime('now','-2 days')");
            $rate = $db->prepare("SELECT SUM(CASE WHEN email_hash=:email THEN 1 ELSE 0 END) AS email_count,SUM(CASE WHEN ip_hash=:ip THEN 1 ELSE 0 END) AS ip_count FROM password_reset_requests WHERE created_at >= datetime('now','-15 minutes')");
            $rate->execute(['email'=>$emailHash,'ip'=>$ipHash]); $counts=$rate->fetch() ?: [];
            if (filter_var($email,FILTER_VALIDATE_EMAIL) && (int)($counts['email_count']??0)<3 && (int)($counts['ip_count']??0)<10) {
                $lookup=$db->prepare('SELECT id,email FROM users WHERE email=:email COLLATE NOCASE AND is_active=1'); $lookup->execute(['email'=>$email]); $account=$lookup->fetch();
                $code = $account ? str_pad((string)random_int(0,999999),6,'0',STR_PAD_LEFT) : '';
                $expires = gmdate('Y-m-d H:i:s',time()+900);
                $insert=$db->prepare('INSERT INTO password_reset_requests(user_id,email_hash,ip_hash,code_hash,expires_at) VALUES(:user,:email,:ip,:code,:expires)');
                $insert->execute(['user'=>$account?(int)$account['id']:null,'email'=>$emailHash,'ip'=>$ipHash,'code'=>$code!==''?password_hash($code,PASSWORD_DEFAULT):'','expires'=>$expires]);
                if ($account) {
                    try {
                        $resetBody="مرحبًا،

رمز التحقق لإعادة تعيين كلمة المرور هو: {$code}

ينتهي الرمز خلال 15 دقيقة، ويستخدم مرة واحدة فقط. إذا لم تطلب ذلك فتجاهل هذه الرسالة.

أثر";
                        $sent=Mailer::send($db,$email,'رمز إعادة تعيين كلمة مرور أثر',$resetBody,Mailer::brandedHtml('رمز إعادة تعيين كلمة المرور','استخدم رمز التحقق التالي لإكمال إعادة تعيين كلمة المرور.',$code,'ينتهي الرمز خلال 15 دقيقة ويُستخدم مرة واحدة فقط. إذا لم تطلب إعادة التعيين فتجاهل هذه الرسالة.'));
                        if (!$sent) throw new RuntimeException('mail() رفض إرسال الرسالة.');
                    } catch (Throwable $mailError) { error_log('[Athar mail] ' . $mailError->getMessage()); Security::event($db,(int)$account['id'],'password_reset_email_failed','/forgot-password'); }
                }
            } else { Security::event($db,null,'password_reset_rate_limited','/forgot-password'); }
            $resetUrl='/reset-password?email='.rawurlencode($email);
            render('forgot_password',['pageTitle'=>'نسيت كلمة المرور','currentUser'=>null,'email'=>$email,'sent'=>true,'resetUrl'=>$resetUrl,'notice'=>'إذا كان البريد مرتبطًا بحساب نشط، فسيصلك رمز تحقق خلال لحظات. تحقّق من مجلد الرسائل غير المرغوب فيها.']); exit;
        }

        if ($path === '/reset-password' && !$user) {
            $email=mb_strtolower(trim(is_string($_POST['email']??null)?$_POST['email']:''));
            $code=trim(is_string($_POST['code']??null)?$_POST['code']:'');
            $password=is_string($_POST['password']??null)?$_POST['password']:'';
            $confirmation=is_string($_POST['password_confirmation']??null)?$_POST['password_confirmation']:'';
            $errors=[];
            if (!filter_var($email,FILTER_VALIDATE_EMAIL)) $errors[]='أدخل البريد الإلكتروني الذي طلبت إليه رمز التحقق.';
            if (!preg_match('/^[0-9]{6}$/',$code)) $errors[]='أدخل رمز التحقق المكوّن من 6 أرقام.';
            if (!valid_password($password)) $errors[]='كلمة المرور يجب أن تكون 12 حرفًا على الأقل وألا تتجاوز 72 بايت.';
            if ($password!==$confirmation) $errors[]='تأكيد كلمة المرور غير مطابق.';
            $request=null;
            if (!$errors) {
                $find=$db->prepare("SELECT * FROM password_reset_requests WHERE email_hash=:email AND used_at IS NULL AND attempts<5 AND expires_at>datetime('now') ORDER BY id DESC LIMIT 1");
                $find->execute(['email'=>Security::fingerprint($email)]); $request=$find->fetch();
                if (!$request || !(int)($request['user_id']??0) || !password_verify($code,(string)$request['code_hash'])) {
                    if ($request) { $db->prepare('UPDATE password_reset_requests SET attempts=attempts+1,used_at=CASE WHEN attempts+1>=5 THEN CURRENT_TIMESTAMP ELSE used_at END WHERE id=:id')->execute(['id'=>$request['id']]); }
                    $errors[]='الرمز غير صحيح أو منتهي الصلاحية. اطلب رمزًا جديدًا وحاول مجددًا.';
                    Security::event($db,null,'password_reset_failed','/reset-password');
                }
            }
            if ($errors) { render('reset_password',['pageTitle'=>'إعادة تعيين كلمة المرور','currentUser'=>null,'email'=>$email,'errors'=>$errors],422); exit; }
            $db->beginTransaction();
            try {
                $claim=$db->prepare("UPDATE password_reset_requests SET used_at=CURRENT_TIMESTAMP WHERE id=:id AND used_at IS NULL AND attempts<5 AND expires_at>datetime('now')");
                $claim->execute(['id'=>$request['id']]);
                if ($claim->rowCount()!==1) { $db->rollBack(); render('reset_password',['pageTitle'=>'إعادة تعيين كلمة المرور','currentUser'=>null,'email'=>$email,'errors'=>['انتهت صلاحية الرمز أو استُخدم بالفعل. اطلب رمزًا جديدًا.']],422); exit; }
                $changed=$db->prepare('UPDATE users SET password_hash=:hash,session_version=session_version+1 WHERE id=:id AND is_active=1');
                $changed->execute(['hash'=>password_hash($password,PASSWORD_DEFAULT),'id'=>$request['user_id']]);
                if ($changed->rowCount()!==1) throw new RuntimeException('الحساب غير نشط.');
                $db->prepare('UPDATE password_reset_requests SET used_at=CURRENT_TIMESTAMP WHERE user_id=:user AND used_at IS NULL')->execute(['user'=>$request['user_id']]);
                $db->commit();
            } catch (Throwable $resetError) { if($db->inTransaction())$db->rollBack(); throw $resetError; }
            Security::event($db,(int)$request['user_id'],'password_reset_success','/reset-password');
            flash('success','تم تحديث كلمة المرور. يمكنك الآن تسجيل الدخول.'); redirect('/login');
        }

        if ($path === '/register' && !$user) {
            $name=clean_value($_POST['full_name']??'',100); $email=mb_strtolower(trim(is_string($_POST['email']??null)?$_POST['email']:''));
            $role=is_string($_POST['role']??null)?$_POST['role']:''; $password=is_string($_POST['password']??null)?$_POST['password']:''; $errors=[];
            if (!Security::registrationAllowed($db,$email)) { Security::event($db,null,'registration_rate_limited','/register'); render('register',['pageTitle'=>'إنشاء حساب','currentUser'=>null,'errors'=>['تم إيقاف التسجيل مؤقتًا بسبب كثرة المحاولات. حاول لاحقًا.'],'old'=>['full_name'=>$name,'email'=>$email,'role'=>$role]],429); exit; }
            if (!empty($_POST['accept_policies'])!==true) $errors[]='يجب الموافقة على سياسة الخصوصية وسياسة النشر لإنشاء الحساب.';
            if (mb_strlen($name)<2) $errors[]='اكتب الاسم بطول حرفين على الأقل.';
            if (!filter_var($email,FILTER_VALIDATE_EMAIL) || mb_strlen($email)>190) $errors[]='أدخل بريدًا إلكترونيًا صحيحًا.';
            if (!in_array($role,['teacher','student'],true)) $errors[]='اختر نوع الحساب.';
            if (!valid_password($password)) $errors[]='كلمة المرور يجب أن تكون 12 حرفًا على الأقل وألا تتجاوز 72 بايت.';
            if ($errors) { Security::recordLogin($db,$email,false); render('register',['pageTitle'=>'إنشاء حساب','currentUser'=>null,'errors'=>$errors,'old'=>['full_name'=>$name,'email'=>$email,'role'=>$role]],422); exit; }
            try {
                $db->beginTransaction(); $id=$auth->createUser($name,$email,$role,$password); $portfolios->createForUser($id,$role);
                $policyRows=$db->query('SELECT policy_key,version FROM site_policies')->fetchAll(); $accept=$db->prepare('INSERT OR IGNORE INTO policy_acceptances(user_id,policy_key,version) VALUES(:user,:policy,:version)');
                foreach($policyRows as $policy) $accept->execute(['user'=>$id,'policy'=>$policy['policy_key'],'version'=>$policy['version']]);
                $trial=$db->prepare("INSERT OR IGNORE INTO user_trials(user_id,starts_at,ends_at,status) VALUES(:user,CURRENT_TIMESTAMP,datetime('now','+14 days'),'active')"); $trial->execute(['user'=>$id]);
                $db->commit(); Security::recordLogin($db,$email,true);
                $auth->signInById($id); flash('success','تم إنشاء حسابك وملفك الأولي.'); redirect('/dashboard');
            } catch (PDOException $exception) {
                if ($db->inTransaction()) $db->rollBack(); Security::recordLogin($db,$email,false);
                if (str_contains($exception->getMessage(),'UNIQUE')) { render('register',['pageTitle'=>'إنشاء حساب','currentUser'=>null,'errors'=>['هذا البريد مسجل بالفعل. سجّل الدخول بدلًا من ذلك.'],'old'=>['full_name'=>$name,'email'=>$email,'role'=>$role]],409); exit; }
                throw $exception;
            }
        }

        if ($path === '/login' && !$user) {
            $email=is_string($_POST['email']??null)?trim($_POST['email']):''; $password=is_string($_POST['password']??null)?$_POST['password']:'';
            $blockedStmt=$db->prepare("SELECT b.reason,b.banned_until FROM user_bans b JOIN users u ON u.id=b.user_id WHERE u.email=:email COLLATE NOCASE AND (b.banned_until IS NULL OR b.banned_until>CURRENT_TIMESTAMP) LIMIT 1"); $blockedStmt->execute(['email'=>$email]); $blocked=$blockedStmt->fetch();
            if ($blocked) { Security::event($db,null,'banned_login_attempt','/login',$email); render('login',['pageTitle'=>'تسجيل الدخول','currentUser'=>null,'errors'=>['تم حظر هذا الحساب مؤقتًا.'.(!empty($blocked['reason'])?' السبب: '.$blocked['reason']:'')],'blockedUntil'=>$blocked['banned_until'],'old'=>['email'=>$email]],403); exit; }
            if (!Security::loginAllowed($db,$email)) { Security::event($db,null,'login_rate_limited',$path,'تجاوز حد المحاولات'); render('login',['pageTitle'=>'تسجيل الدخول','currentUser'=>null,'errors'=>['تم إيقاف المحاولات مؤقتًا لحماية الحساب. انتظر 15 دقيقة ثم حاول مجددًا.'],'old'=>['email'=>$email]],429); exit; }
            $signedIn=$auth->signIn($email,$password); Security::recordLogin($db,$email,$signedIn);
            if ($signedIn) { Security::event($db,(int)($auth->user()['id']??0),'login_success',$path); flash('success','أهلًا بعودتك.'); redirect('/dashboard'); }
            Security::event($db,null,'login_failed',$path,'بيانات غير صحيحة');
            render('login',['pageTitle'=>'تسجيل الدخول','currentUser'=>null,'errors'=>['بيانات الدخول غير صحيحة. تحقق منها وحاول مجددًا.'],'old'=>['email'=>$email]],422); exit;
        }

        if ($path === '/logout' && $user) { $auth->signOut(); redirect('/'); }

        if ($path === '/account/delete' && $user) {
            $confirmation = clean_value($_POST['delete_confirmation'] ?? '', 20);
            $password = is_string($_POST['account_password'] ?? null) ? $_POST['account_password'] : '';
            $hashStmt = $db->prepare('SELECT password_hash FROM users WHERE id=:id');
            $hashStmt->execute(['id'=>(int)$user['id']]);
            $passwordHash = (string)$hashStmt->fetchColumn();
            if ($confirmation !== 'DELETE' || $password === '' || $passwordHash === '' || !password_verify($password, $passwordHash)) {
                flash('error', 'اكتب DELETE وأدخل كلمة المرور الحالية لحذف الحساب.');
                redirect('/settings');
            }
            if ((int)($user['is_owner'] ?? 0) === 1 && (int)$db->query('SELECT COUNT(*) FROM users WHERE is_owner=1 AND is_active=1')->fetchColumn() <= 1) {
                flash('error', 'لا يمكن حذف حساب مالك الموقع الوحيد. عيّن مالكًا آخر أولًا.');
                redirect('/settings');
            }
            $db->beginTransaction();
            try {
                $delete = $db->prepare('DELETE FROM users WHERE id=:id');
                $delete->execute(['id'=>(int)$user['id']]);
                $db->commit();
            } catch (Throwable $deleteError) {
                if ($db->inTransaction()) $db->rollBack();
                throw $deleteError;
            }
            $auth->signOut();
            redirect('/');
        }
        if ($path === '/account/settings' && $user) {
            $name=clean_value($_POST['full_name']??'',120); $email=mb_strtolower(trim((string)($_POST['email']??''))); $currentPassword=(string)($_POST['current_password']??''); $newPassword=(string)($_POST['new_password']??'');
            $hashStmt=$db->prepare('SELECT password_hash FROM users WHERE id=:id'); $hashStmt->execute(['id'=>(int)$user['id']]); $hash=(string)$hashStmt->fetchColumn();
            if (mb_strlen($name)<2 || !filter_var($email,FILTER_VALIDATE_EMAIL)) { flash('error','تحقق من الاسم والبريد الإلكتروني.'); redirect('/settings'); }
            if ($newPassword!=='' && (!password_verify($currentPassword,$hash) || !valid_password($newPassword))) { flash('error','لتغيير كلمة المرور أدخل كلمة المرور الحالية، ويجب أن تكون الجديدة 12 حرفًا على الأقل.'); redirect('/settings'); }
            try {
                if ($newPassword!=='') { $stmt=$db->prepare('UPDATE users SET full_name=:name,email=:email,password_hash=:hash,session_version=session_version+1 WHERE id=:id'); $stmt->execute(['name'=>$name,'email'=>$email,'hash'=>password_hash($newPassword,PASSWORD_DEFAULT),'id'=>$user['id']]); }
                else { $stmt=$db->prepare('UPDATE users SET full_name=:name,email=:email WHERE id=:id'); $stmt->execute(['name'=>$name,'email'=>$email,'id'=>$user['id']]); }
                flash('success','تم تحديث إعدادات الحساب والأمان. سجّل الدخول مجددًا إذا غيّرت كلمة المرور.');
            } catch (PDOException $exception) { flash('error',str_contains($exception->getMessage(),'UNIQUE')?'البريد الإلكتروني مستخدم بالفعل.':'تعذر حفظ إعدادات الحساب.'); }
            redirect('/settings');
        }

        if ($path === '/onboarding/done' && $user) {
            $stmt=$db->prepare('UPDATE users SET onboarding_seen=1 WHERE id=:id'); $stmt->execute(['id'=>$user['id']]);
            redirect('/dashboard');
        }

        if ($path === '/editor' && $user) {
            $title=clean_value($_POST['title']??'',100); $intro=clean_value($_POST['introduction']??'',1200); $input=$_POST['sections']??[]; $errors=[]; $sections=[];
            if (mb_strlen($title)<2) $errors[]='عنوان الملف مطلوب (حرفان على الأقل).';
            if (!is_array($input) || count($input)>100) { $errors[]='يمكن أن يحتوي الملف على 100 عنصر كحد أقصى.'; $input=[]; }
            $currentPortfolio=$portfolios->findForUser((int)$user['id']);
            $templateId=clean_value($_POST['template_id']??($currentPortfolio['template_id']??'classic-sand'),80);
            $template=TemplateCatalog::find($templateId); if (!$template || !in_array($template['role'],[$user['role'],'both'],true)) $template=TemplateCatalog::find(($user['role']??'student')==='teacher'?'classic-sand':'timeline-sand');
            $background=clean_value($_POST['background_color']??($currentPortfolio['background_color']??'#fbf7ef'),7);
            $accent=clean_value($_POST['accent_color']??($currentPortfolio['accent_color']??'#c18a35'),7);
            if (!preg_match('/^#[0-9a-fA-F]{6}$/',$background) || !preg_match('/^#[0-9a-fA-F]{6}$/',$accent)) $errors[]='اختر لون خلفية ولون لمسات صالحين.';
            $fonts=array_merge(['system-ui','Tahoma','Arial','Georgia'],TemplateCatalog::fonts()); $font=clean_value($_POST['font_family']??'system-ui',40); if (!in_array($font,$fonts,true)) $font='system-ui';
            $covers=['clean','ornament','editorial','mono']; $cover=clean_value($_POST['cover_style']??'clean',30); if (!in_array($cover,$covers,true)) $cover='clean';
            $displayName=clean_value($_POST['display_name']??'',100);
            foreach (array_values($input) as $section) {
                if (!is_array($section)) continue;
                $sectionTitle=clean_value($section['title']??'',80); if ($sectionTitle==='') continue;
                $parentRef=is_string($section['parent_ref']??null)?clean_value($section['parent_ref'],80):(string)(int)($section['parent_id']??0);
                $sections[]=['id'=>(int)($section['id']??0),'client_key'=>clean_value($section['client_key']??'',80),'title'=>$sectionTitle,'body'=>clean_value($section['body']??'',5000),'visibility'=>($section['visibility']??'private')==='public'?'public':'private','enabled'=>!empty($section['enabled'])?1:0,'parent_ref'=>$parentRef,'display_mode'=>in_array(($section['display_mode']??'auto'),['auto','page','compact'],true)?$section['display_mode']:'auto','subject_theme'=>array_key_exists((string)($section['subject_theme']??''),subject_theme_options())?(string)$section['subject_theme']:'general'];
            }
            $sectionLimit=SubscriptionService::limit($db,(int)$user['id'],'max_sections'); $mainSectionCount=count(array_filter($sections,static fn(array $section):bool=>($section['parent_ref']??'0')==='0' || (int)($section['parent_ref']??0)===0)); if ($mainSectionCount>$sectionLimit) $errors[]='انتهت سعة الباقة: الحد الأقصى '.$sectionLimit.' قسمًا رئيسيًا. اختر خطة أعلى من صفحة الخطط ثم أعد المحاولة.';
            if (count($sections)<1) $errors[]='يجب أن يحتوي الملف على قسم واحد على الأقل.';
            if ($errors) { render('editor',['pageTitle'=>'محرر الملف','currentUser'=>$user,'portfolio'=>$currentPortfolio,'errors'=>$errors],422); exit; }
            $portfolios->saveForUser((int)$user['id'],$title,$intro,$sections);
            $portfolios->setTemplate((int)$user['id'],$template['id'],['display_name'=>$displayName,'background_color'=>$background,'accent_color'=>$accent,'font_family'=>$font,'cover_style'=>$cover]);
            $portfolio=$portfolios->findForUser((int)$user['id']); audit_event($db,(int)$user['id'],(int)$portfolio['id'],'portfolio_saved','تم حفظ ملف الإنجاز');
            flash('success','حُفظت تغييرات ملفك بنجاح.'); redirect('/editor');
        }

        if ($path === '/support' && $user) {
            $subject=clean_value($_POST['subject']??'',120); $message=clean_value($_POST['message']??'',4000);
            if ($subject==='' || mb_strlen($message)<10) { flash('error','اكتب عنوان البلاغ ورسالة لا تقل عن 10 أحرف.'); redirect('/support'); }
            $stmt=$db->prepare('INSERT INTO support_requests (user_id,subject,message) VALUES (:user,:subject,:message)');
            $stmt->execute(['user'=>$user['id'],'subject'=>$subject,'message'=>$message]);
            $sent=notify_owner_by_email($db,$user,'بلاغ دعم جديد: '.$subject,$message);
            flash($sent?'success':'warning',$sent?'وصل بلاغك وتم إرسال إشعار إلى بريد الإدارة.':'حُفظ بلاغك داخل الموقع، لكن تعذر إرسال إشعار البريد. سيتابعه المالك من لوحة الدعم.'); redirect('/support');
        }
        if ($path === '/subscription-request' && $user) {
            $planId=(int)($_POST['plan_id']??0); $message=clean_value($_POST['message']??'',1500);
            $planStmt=$db->prepare('SELECT id,name,price_minor,currency,duration_days FROM subscription_plans WHERE id=:id AND is_active=1'); $planStmt->execute(['id'=>$planId]); $plan=$planStmt->fetch();
            if (!$plan) { flash('error','الخطة المطلوبة غير موجودة.'); redirect('/plans'); }
            $subject='طلب اشتراك: '.(string)$plan['name']; $fullMessage='الخطة المطلوبة: '.(string)$plan['name'].' | المدة: '.(int)$plan['duration_days'].' يومًا'.($message!==''?'\n\nرسالة المستخدم:\n'.$message:'');
            $stmt=$db->prepare('INSERT INTO support_requests (user_id,subject,message) VALUES (:user,:subject,:message)'); $stmt->execute(['user'=>$user['id'],'subject'=>$subject,'message'=>$fullMessage]);
            $sent=notify_owner_by_email($db,$user,$subject,$fullMessage);
            flash($sent?'success':'warning',$sent?'تم رفع طلب الاشتراك وإرسال إشعار إلى بريد الإدارة.':'تم حفظ طلب الاشتراك داخل الموقع، لكن تعذر إرسال إشعار البريد.'); redirect('/plans');
        }
        if ($path === '/review' && $user) {
            $rating=filter_var($_POST['rating']??0,FILTER_VALIDATE_INT,['options'=>['min_range'=>1,'max_range'=>5]]); $message=clean_value($_POST['message']??'',1200);
            if ($rating===false) { flash('error','اختر تقييمًا من نجمة إلى خمس نجوم.'); redirect('/support'); }
            $stmt=$db->prepare('INSERT INTO site_reviews (user_id,rating,message) VALUES (:user,:rating,:message)'); $stmt->execute(['user'=>$user['id'],'rating'=>$rating,'message'=>$message]);
            flash('success','شكرًا لك. وصل تقييمك إلى فريق أثر للمراجعة.'); redirect('/support');
        }
        if ($path === '/owner/support/update' && $user) {
            require_owner($user); $id=(int)($_POST['request_id']??0); $status=clean_value($_POST['status']??'new',20); if (!in_array($status,['new','in_progress','resolved'],true)) $status='new';
            $note=clean_value($_POST['owner_note']??'',1000); $requestStmt=$db->prepare('SELECT r.subject,r.message,u.email,u.full_name FROM support_requests r JOIN users u ON u.id=r.user_id WHERE r.id=:id'); $requestStmt->execute(['id'=>$id]); $request=$requestStmt->fetch();
            $stmt=$db->prepare('UPDATE support_requests SET status=:status,owner_note=:note,updated_at=CURRENT_TIMESTAMP WHERE id=:id'); $stmt->execute(['status'=>$status,'note'=>$note,'id'=>$id]);
            $sent=false; if($request && $note!==''){try{$sent=Mailer::send($db,(string)$request['email'],'رد جديد على بلاغك: '.(string)$request['subject'],$note,Mailer::brandedHtml('رد على بلاغك',(string)$note,'','يمكنك متابعة حالة البلاغ من مركز المساعدة داخل منصة أثر.'));}catch(Throwable $mailError){error_log('[Athar support reply] '.$mailError->getMessage());}}
            flash($sent?'success':'warning',$sent?'تم تحديث البلاغ وإرسال الرد إلى بريد المستخدم.':'تم تحديث حالة البلاغ داخل الموقع، لكن تعذر إرسال البريد.'); redirect('/owner/support');
        }
        if ($path === '/owner/review/update' && $user) {
            require_owner($user); $id=(int)($_POST['review_id']??0); $published=!empty($_POST['is_published'])?1:0;
            $stmt=$db->prepare('UPDATE site_reviews SET is_published=:published,owner_note=:note,updated_at=CURRENT_TIMESTAMP WHERE id=:id'); $stmt->execute(['published'=>$published,'note'=>clean_value($_POST['owner_note']??'',500),'id'=>$id]);
            flash('success','تم تحديث حالة التقييم.'); redirect('/owner/support');
        }

        if ($path === '/profile' && $user) {
            $currentProfile=$portfolios->findForUser((int)$user['id']);
            $avatarName=$currentProfile['avatar_stored_name']??null; $avatarMime=$currentProfile['avatar_mime_type']??null;
            if (!empty($_FILES['avatar_file']) && ($_FILES['avatar_file']['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_NO_FILE) {
                $avatar=Storage::save($_FILES['avatar_file']);
                if (!str_starts_with((string)$avatar['mime_type'],'image/')) { Storage::delete($avatar['stored_name']); throw new RuntimeException('الصورة الشخصية يجب أن تكون صورة.'); }
                if ($avatarName) Storage::delete((string)$avatarName);
                $avatarName=$avatar['stored_name']; $avatarMime=$avatar['mime_type'];
            }
            $portfolios->saveProfile((int)$user['id'],[
                'headline'=>clean_value($_POST['headline']??'',140),'school_name'=>clean_value($_POST['school_name']??'',140),
                'grade_level'=>clean_value($_POST['grade_level']??'',80),'subject'=>clean_value($_POST['subject']??'',100),
                'avatar_stored_name'=>$avatarName,'avatar_mime_type'=>$avatarMime,'show_avatar'=>isset($_POST['show_avatar'])?1:0,
            ]);
            flash('success','تم حفظ الهوية التعليمية.'); redirect('/profile');
        }

        if ($path === '/sharing/presentation' && $user) {
            $presentation=clean_value($_POST['public_presentation']??'landing',30);
            $portfolios->setPublicPresentation((int)$user['id'],$presentation);
            flash('success','تم حفظ النمط الافتراضي لرابط المشاركة.');
            redirect('/settings');
        }

        if ($path === '/sharing' && $user) {
            $action=is_string($_POST['action']??null)?$_POST['action']:'enable'; $portfolio=$portfolios->findForUser((int)$user['id']);
            $enabled=$action!=='disable'; if($enabled) require_subscription_feature($db,(int)$user['id'],'public_sharing');
            $shareSlug=strtolower(trim((string)($_POST['share_slug']??'')));
            if ($enabled && ($shareSlug!=='' || empty($portfolio['share_token']))) {
                if (!preg_match('/^[a-z0-9][a-z0-9-]{2,79}$/',$shareSlug)) { flash('error','اسم الرابط يجب أن يكون بالإنجليزية الصغيرة، أرقامًا أو شرطات، من 3 إلى 80 حرفًا؛ مثال: my-portfolio.'); redirect('/settings'); }
                $slugCheck=$db->prepare('SELECT user_id FROM portfolios WHERE share_token=:token AND user_id<>:user LIMIT 1'); $slugCheck->execute(['token'=>$shareSlug,'user'=>$user['id']]);
                if ($slugCheck->fetchColumn()) { flash('error','اسم الرابط مستخدم بالفعل. اختر اسمًا مختلفًا.'); redirect('/settings'); }
            }
            $token=$portfolios->updateSharing((int)$user['id'],$enabled,isset($_POST['show_name']),isset($_POST['quick_share']),$action==='regenerate',$shareSlug!==''?$shareSlug:null);
            $portfolios->setPublicViewMode((int)$user['id'], clean_value($_POST['public_view_mode']??($portfolio['public_view_mode']??'website'),20));
            $layout=clean_value($_POST['public_layout_mode']??($portfolio['public_layout_mode']??'single'),20); $portfolios->setPublicLayoutMode((int)$user['id'], in_array($layout,['single','sections','subjects'],true)?$layout:'single');
            $portfolios->setSectionStructure((int)$user['id'], clean_value($_POST['section_structure']??($portfolio['section_structure']??'flat'),20));
            $portfolios->setPublicSections((int)$user['id'], is_array($_POST['public_sections']??null)?$_POST['public_sections']:[]);
            audit_event($db,(int)$user['id'],(int)$portfolio['id'],$enabled?'sharing_enabled':'sharing_disabled',$enabled?'تم تفعيل رابط مشاركة':'تم إيقاف رابط المشاركة');
            if ($enabled) $_SESSION['new_share_url']='/p/'.$token;
            flash('success',$enabled?($action==='regenerate'?'تم تجديد رابط المشاركة وإبطال الرابط السابق.':'تم تفعيل رابط المشاركة. راجع الأقسام التي تريد إظهارها قبل الإرسال.'):'تم إيقاف رابط المشاركة.');
            redirect('/settings');
        }

        if ($path === '/owner/user' && $user) {
            require_owner($user); $action=clean_value($_POST['action']??'',30); $targetId=(int)($_POST['user_id']??0); $target=$db->prepare('SELECT * FROM users WHERE id=:id'); $target->execute(['id'=>$targetId]); $target=$target->fetch();
            if ($action==='create') {
                $name=clean_value($_POST['full_name']??'',120); $email=mb_strtolower(trim((string)($_POST['email']??''))); $password=(string)($_POST['password']??''); $role=in_array($_POST['role']??'', ['teacher','student'], true)?$_POST['role']:'student';
                if (mb_strlen($name)<2 || !filter_var($email,FILTER_VALIDATE_EMAIL) || !valid_password($password)) { flash('error','تحقق من الاسم والبريد وكلمة المرور (12 حرفًا على الأقل).'); redirect('/owner/users'); }
                try { $newId=$auth->createUser($name,$email,$role,$password); Security::event($db,(int)$user['id'],'admin_create_user','/owner/user','user='.$newId); flash('success','تم إنشاء الحساب بنجاح.'); } catch (Throwable $e) { flash('error','تعذر إنشاء الحساب. قد يكون البريد مستخدمًا.'); } redirect('/owner/users');
            }
            if (!$target) route_error('الحساب غير موجود.',404);
            if ($targetId===(int)$user['id'] && in_array($action,['delete','toggle_active','demote','ban','unban'],true)) { flash('error','لا يمكنك تعطيل أو حذف أو سحب صلاحية حسابك.'); redirect('/owner/users'); }
            if ($action==='toggle_active') { $db->prepare('UPDATE users SET is_active=CASE WHEN is_active=1 THEN 0 ELSE 1 END,session_version=session_version+1 WHERE id=:id AND id<>:self')->execute(['id'=>$targetId,'self'=>$user['id']]); }
            elseif ($action==='role') { $role=in_array($_POST['role']??'', ['teacher','student'], true)?$_POST['role']:'student'; $db->prepare('UPDATE users SET role=:role WHERE id=:id')->execute(['role'=>$role,'id'=>$targetId]); }
            elseif ($action==='promote') { $db->prepare('UPDATE users SET is_owner=1 WHERE id=:id')->execute(['id'=>$targetId]); }
            elseif ($action==='demote') { $owners=(int)$db->query('SELECT COUNT(*) FROM users WHERE is_owner=1')->fetchColumn(); if ($owners<=1) { flash('error','يجب أن يبقى مالك واحد على الأقل.'); redirect('/owner/users'); } $db->prepare('UPDATE users SET is_owner=0 WHERE id=:id')->execute(['id'=>$targetId]); }
            elseif ($action==='reset_password') { $password=(string)($_POST['password']??''); if(!valid_password($password)){flash('error','كلمة المرور يجب أن تكون 12 حرفًا على الأقل وألا تتجاوز 72 بايت.');redirect('/owner/users');} $db->prepare('UPDATE users SET password_hash=:hash,session_version=session_version+1 WHERE id=:id')->execute(['hash'=>password_hash($password,PASSWORD_DEFAULT),'id'=>$targetId]); }
            elseif ($action==='ban') {
                if ((int)$target['is_owner']===1) { flash('error','لا يمكن حظر حساب مالك آخر.'); redirect('/owner/users'); }
                $reason=clean_value($_POST['reason']??'',300); $days=filter_var($_POST['days']??0,FILTER_VALIDATE_INT,['options'=>['min_range'=>0,'max_range'=>3650]]);
                if (mb_strlen($reason)<5 || $days===false) { flash('error','أدخل سببًا واضحًا ومدة صحيحة؛ صفر يعني حظرًا دائمًا.'); redirect('/owner/users'); }
                $until=$days>0?gmdate('Y-m-d H:i:s',time()+$days*86400):null;
                $stmt=$db->prepare('INSERT INTO user_bans(user_id,reason,banned_until,created_by) VALUES(:id,:reason,:until,:owner) ON CONFLICT(user_id) DO UPDATE SET reason=excluded.reason,banned_until=excluded.banned_until,created_by=excluded.created_by,created_at=CURRENT_TIMESTAMP'); $stmt->execute(['id'=>$targetId,'reason'=>$reason,'until'=>$until,'owner'=>$user['id']]); Security::invalidateUserSessions($db,$targetId);
            }
            elseif ($action==='unban') { $db->prepare('DELETE FROM user_bans WHERE user_id=:id')->execute(['id'=>$targetId]); Security::invalidateUserSessions($db,$targetId); }
            elseif ($action==='delete') { $db->prepare('DELETE FROM users WHERE id=:id AND is_owner=0')->execute(['id'=>$targetId]); }
            else route_error('الإجراء الإداري غير معروف.',400);
            Security::event($db,(int)$user['id'],'admin_'.$action,'/owner/user','user='.$targetId); flash('success','تم تطبيق الإجراء الإداري.'); redirect('/owner/users');
        }

        if ($path === '/owner/notification' && $user) {
            require_owner($user);
            $target=clean_value($_POST['target']??'online',20); $title=clean_value($_POST['title']??'',100); $message=clean_value($_POST['message']??'',500); $type=in_array($_POST['type']??'', ['info','success','error'],true)?$_POST['type']:'info';
            if ($title==='' || $message==='') { flash('error','اكتب عنوان الرسالة ونصها.'); redirect('/owner/users'); }
            if ($target==='online') { $stmt=$db->query("SELECT id FROM users WHERE is_active=1 AND last_seen_at IS NOT NULL AND last_seen_at >= datetime('now','-10 minutes')"); }
            else { $stmt=$db->prepare('SELECT id FROM users WHERE id=:id AND is_active=1'); $stmt->execute(['id'=>(int)$target]); }
            $insert=$db->prepare('INSERT INTO admin_notifications(user_id,title,message,type,created_by) VALUES(:user,:title,:message,:type,:owner)'); $count=0; foreach($stmt->fetchAll(PDO::FETCH_COLUMN) as $id) { $insert->execute(['user'=>$id,'title'=>$title,'message'=>$message,'type'=>$type,'owner'=>$user['id']]); $count++; }
            flash('success','تم إرسال Toast إلى '.$count.' مستخدم متصل.'); redirect('/owner/users');
        }

        if ($path === '/owner/portfolio' && $user) {
            require_owner($user);
            $portfolioId=(int)($_POST['portfolio_id']??0); $action=clean_value($_POST['action']??'',30);
            $lookup=$db->prepare('SELECT user_id FROM portfolios WHERE id=:id'); $lookup->execute(['id'=>$portfolioId]); $ownerUserId=(int)$lookup->fetchColumn();
            if (!$ownerUserId) route_error('ملف الإنجاز غير موجود.',404);
            if ($action==='view_mode') $portfolios->setPublicViewMode($ownerUserId, clean_value($_POST['public_view_mode']??'website',20));
            elseif ($action==='sharing') $portfolios->updateSharing($ownerUserId,!empty($_POST['sharing_enabled']),!empty($_POST['show_name']),!empty($_POST['quick_share']));
            else route_error('الإجراء غير معروف.');
            flash('success','تم تطبيق تعديل المالك على الملف.'); redirect('/owner');
        }

        if ($path === '/growth/goal' && $user) {
            $title=clean_value($_POST['title']??'',180); $date=clean_value($_POST['target_date']??'',10);
            if ($title==='') { flash('error','اكتب هدفًا قبل الحفظ.'); redirect('/growth'); }
            $stmt=$db->prepare('INSERT INTO portfolio_goals (user_id,title,target_date) VALUES (:user,:title,:date)'); $stmt->execute(['user'=>$user['id'],'title'=>$title,'date'=>$date]);
            flash('success','تمت إضافة الهدف إلى خطة تقدمك.'); redirect('/growth');
        }
        if (preg_match('#^/growth/goal/(\d+)/toggle$#',$path,$match) && $user) {
            $stmt=$db->prepare("UPDATE portfolio_goals SET status=CASE WHEN status='done' THEN 'active' ELSE 'done' END,updated_at=CURRENT_TIMESTAMP WHERE id=:id AND user_id=:user"); $stmt->execute(['id'=>(int)$match[1],'user'=>$user['id']]); redirect('/growth');
        }
        if ($path === '/growth/reflection' && $user) {
            $prompt=clean_value($_POST['prompt']??'تأمل اليوم',180); $body=clean_value($_POST['body']??'',4000);
            if ($body==='') { flash('error','اكتب تأملًا قصيرًا قبل الحفظ.'); redirect('/growth'); }
            $stmt=$db->prepare('INSERT INTO portfolio_reflections (user_id,prompt,body) VALUES (:user,:prompt,:body)'); $stmt->execute(['user'=>$user['id'],'prompt'=>$prompt,'body'=>$body]);
            flash('success','تم حفظ تأملك في سجل التعلم.'); redirect('/growth');
        }

        if ($path === '/evidence/reorder' && $user) {
            $evidenceRepo->reorder((int)$user['id'], is_array($_POST['evidence_order']??null)?$_POST['evidence_order']:[]);
            flash('success','تم حفظ ترتيب الأدلة والصور.'); redirect('/editor#evidence');
        }

        if ($path === '/evidence/upload' && $user) {
            $portfolio=$portfolios->findForUser((int)$user['id']);
            try {
                if (!SubscriptionService::allows($db,(int)$user['id'],'file_upload')) throw new RuntimeException('رفع الملفات غير متاح في باقتك الحالية. اختر خطة تسمح برفع الملفات من صفحة الخطط.');
                if (!$portfolio) throw new RuntimeException('ملف الإنجاز غير موجود.');
                $countStmt=$db->prepare('SELECT COUNT(*) FROM evidence WHERE portfolio_id=:id'); $countStmt->execute(['id'=>$portfolio['id']]);
                $evidenceLimit=SubscriptionService::limit($db,(int)$user['id'],'max_evidence'); if ((int)$countStmt->fetchColumn()>=$evidenceLimit) throw new RuntimeException('انتهت سعة الباقة: الحد الأقصى '.$evidenceLimit.' دليلًا. اختر خطة أعلى من صفحة الخطط ثم أعد المحاولة.');
                $sectionId=filter_var($_POST['section_id']??null,FILTER_VALIDATE_INT); $validIds=array_map('intval',array_column($portfolio['sections'],'id'));
                if (!$sectionId || !in_array((int)$sectionId,$validIds,true)) throw new RuntimeException('اختر قسمًا صحيحًا لربط الدليل.');
                $evidenceType='';
                if (($user['role']??'')==='teacher') {
                    $typeChoice=clean_value($_POST['evidence_type']??'',120);
                    $mainSectionTitle='';
                    foreach (($portfolio['sections']??[]) as $candidate) if ((int)$candidate['id']===(int)$sectionId && empty($candidate['parent_id'])) { $mainSectionTitle=(string)$candidate['title']; break; }
                    if ($mainSectionTitle==='') throw new RuntimeException('اختر عنصرًا رئيسيًا صحيحًا لربط الدليل.');
                    $allowedTypes=PortfolioRepository::teacherWitnessTypes()[$mainSectionTitle]??[];
                    if ($typeChoice==='__other__') {
                        $evidenceType=clean_value($_POST['evidence_type_custom']??'',120);
                        if ($evidenceType==='') throw new RuntimeException('اكتب نوع الشاهد الآخر.');
                    } else {
                        if ($typeChoice==='' || !in_array($typeChoice,$allowedTypes,true)) throw new RuntimeException('اختر نوع الشاهد المرتبط بالعنصر الرئيسي.');
                        $evidenceType=$typeChoice;
                    }
                }
                $source=evidence_source_from_url($_POST['source_url']??'');
                if ($source['url']!=='') {
                    $file=Storage::saveFromUrl($source['url'],$source['kind']);
                    $file['source_url']=$source['url']; $file['source_kind']=$source['kind'];
                } else {
                    if (!isset($_FILES['evidence_file']) || !is_array($_FILES['evidence_file'])) throw new RuntimeException('اختر ملفًا أو أضف رابط الشاهد.');
                    $file=Storage::save($_FILES['evidence_file']);
                }
                try {
                    $evidenceRepo->create((int)$portfolio['id'],(int)$sectionId,$file,[
                        'title'=>clean_value($_POST['title']??'',120),'description'=>clean_value($_POST['description']??'',1000),
                        'evidence_type'=>$evidenceType,'evidence_date'=>clean_value($_POST['evidence_date']??'',10),'tags'=>clean_value($_POST['tags']??'',200),
                        'competency'=>clean_value($_POST['competency']??'',120),
                    ]);
                } catch (Throwable $exception) { if (!empty($file['stored_name'])) Storage::delete($file['stored_name']); throw $exception; }
                audit_event($db,(int)$user['id'],(int)$portfolio['id'],'evidence_added','إضافة دليل جديد');
                flash('success','تم رفع الدليل وحفظه في مساحة خاصة.');
            } catch (RuntimeException $exception) { flash('error',$exception->getMessage()); }
            redirect('/editor#evidence');
        }

        if (preg_match('#^/evidence/(\d+)/(delete|visibility)$#', $path, $match) && $user) {
            $id=(int)$match[1]; $action=$match[2]; $item=$evidenceRepo->owned($id,(int)$user['id']);
            if (!$item) route_error('الدليل غير موجود أو لا تملك صلاحية الوصول إليه.',404);
            if ($action==='delete') { $stored=$evidenceRepo->delete($id,(int)$user['id']); if ($stored) Storage::delete($stored); flash('success','تم حذف الدليل.'); redirect('/editor#evidence'); }
            if ($action==='visibility') { $visibility=(($_POST['visibility']??'private')==='public')?'public':'private'; $evidenceRepo->setVisibility($id,(int)$user['id'],$visibility); flash('success',$visibility==='public'?'سيظهر وصف الدليل عند نشر قسمه ورابطه.':'أصبح الدليل خاصًا.'); redirect('/editor#evidence'); }
        }

        if ($path === '/templates/custom/save' && $user) {
            require_subscription_feature($db,(int)$user['id'],'custom_templates');
            $name=clean_value($_POST['name']??'',80); $layout=clean_value($_POST['layout']??'classic',30);
            $background=clean_value($_POST['background_color']??'#fbf7ef',7); $accent=clean_value($_POST['accent_color']??'#c18a35',7);
            $font=clean_value($_POST['font_family']??'system-ui',30); $cover=clean_value($_POST['cover_style']??'clean',30); $appearanceMode=($user['role']==='teacher' && ($_POST['appearance_mode']??'light')==='dark')?'dark':'light'; $teacherSubject=clean_value($_POST['teacher_subject']??'general',30); if (!array_key_exists($teacherSubject,teacher_subject_options())) $teacherSubject='general'; if ($appearanceMode==='dark') $background='#111827';
            if ($name==='') { flash('error','اكتب اسمًا للقالب المخصص.'); redirect('/templates'); }
            if (!preg_match('/^#[0-9a-fA-F]{6}$/',$background) || !preg_match('/^#[0-9a-fA-F]{6}$/',$accent)) { flash('error','اختر ألوانًا صالحة.'); redirect('/templates'); }
            if (!in_array($layout,['classic','editorial','timeline','gallery','minimal','report','student','folio'],true)) $layout='classic';
            if (!in_array($font,['system-ui','Tahoma','Arial','Georgia'],true)) $font='system-ui';
            if (!in_array($cover,['clean','ornament','editorial','mono'],true)) $cover='clean';
            $isPublic=($user['role']==='teacher' && !empty($_POST['is_public']));
            $id=$portfolios->saveCustomTemplate((int)$user['id'],$name,['layout'=>$layout,'background_color'=>$background,'accent_color'=>$accent,'font_family'=>$font,'cover_style'=>$cover,'appearance_mode'=>$appearanceMode,'teacher_subject'=>$teacherSubject],$isPublic);
            $portfolios->setTemplate((int)$user['id'],$layout.'-sand',['display_name'=>'','background_color'=>$background,'accent_color'=>$accent,'font_family'=>$font,'cover_style'=>$cover,'appearance_mode'=>$appearanceMode]);
            if ($user['role']==='teacher') { $labels=teacher_subject_options(); $db->prepare('UPDATE profiles SET subject=:subject,updated_at=CURRENT_TIMESTAMP WHERE user_id=:id')->execute(['subject'=>$teacherSubject==='general'?'':$labels[$teacherSubject],'id'=>$user['id']]); }
            flash('success','تم حفظ القالب المخصص وتطبيقه على ملفك.'); redirect('/templates');
        }

        if ($path === '/templates/custom/apply' && $user) {
            require_subscription_feature($db,(int)$user['id'],'custom_templates');
            $id=(int)($_POST['custom_id']??0); $stmt=$db->prepare("SELECT t.*,u.role AS template_owner_role FROM user_templates t JOIN users u ON u.id=t.user_id WHERE t.id=:id AND (t.user_id=:user OR (t.is_public=1 AND u.role='teacher'))"); $stmt->execute(['id'=>$id,'user'=>$user['id']]); $custom=$stmt->fetch();
            if (!$custom) { flash('error','القالب المخصص غير موجود.'); redirect('/templates'); }
            $config=json_decode((string)$custom['config_json'],true) ?: []; $layout=in_array(($config['layout']??''),['classic','editorial','timeline','gallery','minimal','report','student','folio'],true)?$config['layout']:'classic';
            $portfolios->setTemplate((int)$user['id'],$layout.'-sand',['display_name'=>'','background_color'=>$config['background_color']??'#fbf7ef','accent_color'=>$config['accent_color']??'#c18a35','font_family'=>$config['font_family']??'system-ui','cover_style'=>$config['cover_style']??'clean','appearance_mode'=>$config['appearance_mode']??'light']);
            flash('success','تم تطبيق القالب المخصص.'); redirect('/templates');
        }

        if ($path === '/templates/apply' && $user) {
            $id=clean_value($_POST['template_id']??'',80); $template=TemplateCatalog::find($id);
            $control=$db->prepare('SELECT is_enabled FROM template_controls WHERE template_id=:id'); $control->execute(['id'=>$id]); $enabledValue=$control->fetchColumn();
            if ($enabledValue!==false && (int)$enabledValue!==1) { flash('error','هذا القالب متوقف حاليًا من إدارة الموقع. اختر قالبًا آخر.'); redirect('/templates'); }
            if (!$template || !in_array($template['role'],[$user['role'],'both'],true)) { flash('error','هذا القالب غير مخصص لنوع حسابك.'); redirect('/templates'); }
            $appearanceMode=($user['role']==='teacher' && ($_POST['appearance_mode']??'light')==='dark')?'dark':'light';
            $background=$appearanceMode==='dark'?'#111827':($user['role']==='teacher'?'#ffffff':clean_value($_POST['background_color']??$template['palette']['surface'],7));
            $accent=clean_value($_POST['accent_color']??$template['palette']['accent'],7);
            if (!preg_match('/^#[0-9a-fA-F]{6}$/',$background) || !preg_match('/^#[0-9a-fA-F]{6}$/',$accent)) { flash('error','اختر ألوانًا صالحة.'); redirect('/templates'); }
            $currentPortfolio=$portfolios->findForUser((int)$user['id']);
            $fonts=array_merge(['system-ui','Tahoma','Arial','Georgia'],TemplateCatalog::fonts()); $font=clean_value($_POST['font_family']??($currentPortfolio['font_family']??'system-ui'),40); if (!in_array($font,$fonts,true)) $font='system-ui';
            $covers=['clean','ornament','editorial','mono']; $cover=clean_value($_POST['cover_style']??($currentPortfolio['cover_style']??'clean'),30); if (!in_array($cover,$covers,true)) $cover='clean';
            $portfolios->setTemplate((int)$user['id'],$id,['display_name'=>clean_value($_POST['display_name']??($currentPortfolio['display_name']??''),100),'background_color'=>$background,'accent_color'=>$accent,'font_family'=>$font,'cover_style'=>$cover,'appearance_mode'=>$appearanceMode]);
            if ($user['role']==='teacher') { $subjectKey=clean_value($_POST['teacher_subject']??'general',30); $subjects=teacher_subject_options(); if (!array_key_exists($subjectKey,$subjects)) $subjectKey='general'; $db->prepare('UPDATE profiles SET subject=:subject,updated_at=CURRENT_TIMESTAMP WHERE user_id=:id')->execute(['subject'=>$subjectKey==='general'?'':$subjects[$subjectKey],'id'=>$user['id']]); }
            if (!empty($_POST['load_demo_content'])) {
                $starter=$user['role']==='teacher'?['أداء الواجبات الوظيفية','التفاعل مع المجتمع المحلي','التفاعل مع أولياء الأمور','التنوع في استراتيجيات التدريس','تحسين نتائج المتعلمين','إعداد وتنفيذ خطة التعلم','توظيف تقنيات ووسائل التعلم المناسبة','تهيئة البيئة التعليمية','الإدارة الصفية','تحليل نتائج المتعلمين وتشخيص مستوياتهم','تنوع أساليب التقويم']:['نبذتي التعليمية','المواد الدراسية','أعمالي ومشروعاتي','شواهدي ودرجاتي','مهاراتي وإنجازاتي','تأملاتي وخطتي القادمة'];
                $portfolios->applyStarterSections((int)$user['id'],$starter);
            }
            flash('success','تم تطبيق القالب. يمكنك تعديل المحتوى النموذجي من المحرر.'); redirect('/editor');
        }

        if ($path === '/assistant' && $user) {
            require_subscription_feature($db,(int)$user['id'],'ai');
            $task=clean_value($_POST['task']??'improve',30); $text=clean_value($_POST['text']??'',6000); $result=''; $provider='local'; $error='';
            if ($text==='') $error='اكتب أو الصق نصًا تختاره بنفسك. لا تُرسل المرفقات تلقائيًا.';
            elseif (!Assistant::isEnabled()) $result=Assistant::localHint($task,$text);
            else {
                try {
                    $count=$db->prepare("SELECT COUNT(*) FROM ai_usage WHERE user_id=:id AND status='success' AND date(created_at,'+3 hours')=date('now','+3 hours')"); $count->execute(['id'=>$user['id']]);
                    if ((int)$count->fetchColumn()>=10) throw new RuntimeException('وصلت إلى الحد اليومي للمساعد المحلي (10 طلبات ناجحة).');
                    $provider=Assistant::provider(); $result=$provider->complete($task,$text,$user['role']);
                    $providerName=$provider instanceof GroqProvider?'groq':($provider instanceof GeminiProvider?'gemini':'manual');
                    $log=$db->prepare('INSERT INTO ai_usage (user_id,task_type,provider,status) VALUES (:user,:task,:provider,:status)'); $log->execute(['user'=>$user['id'],'task'=>$task,'provider'=>$providerName,'status'=>'success']);
                    $provider=$providerName;
                } catch (RuntimeException $exception) { $error=$exception->getMessage(); }
            }
            $portfolio=$portfolios->findForUser((int)$user['id']);
            render('assistant',['pageTitle'=>'مساعد أثر','currentUser'=>$user,'portfolio'=>$portfolio,'task'=>$task,'inputText'=>$text,'assistantResult'=>$result,'assistantError'=>$error,'assistantProvider'=>$provider,'ocrText'=>$_SESSION['ocr_text']??'']); unset($_SESSION['ocr_text']); exit;
        }
        route_error('الصفحة غير متاحة أو تحتاج إلى تسجيل الدخول.',404);
    }

    if ($path === '/notifications/poll' && $user) {
        header('Content-Type: application/json; charset=utf-8');
        $stmt=$db->prepare('SELECT id,title,message,type FROM admin_notifications WHERE user_id=:user AND seen_at IS NULL ORDER BY id ASC LIMIT 5'); $stmt->execute(['user'=>$user['id']]); $items=$stmt->fetchAll();
        if ($items) { $ids=array_column($items,'id'); $marks=implode(',',array_fill(0,count($ids),'?')); $up=$db->prepare("UPDATE admin_notifications SET seen_at=CURRENT_TIMESTAMP WHERE user_id=? AND id IN ($marks)"); $up->execute(array_merge([(int)$user['id']],array_map('intval',$ids))); }
        echo json_encode(['items'=>$items],JSON_UNESCAPED_UNICODE); exit;
    }
    if (preg_match('#^/evidence/(\d+)/preview$#', $path, $match)) {
        require_user($user);
        $item = $evidenceRepo->owned((int)$match[1], (int)$user['id']);
        if (!$item) route_error('الدليل غير موجود.', 404);
        if (!empty($item['source_url']) && empty($item['stored_name'])) { header('Referrer-Policy: no-referrer'); header('Location: '.(string)$item['source_url'], true, 302); exit; }
        try { $filePath = Storage::path((string)$item['stored_name']); }
        catch (Throwable $fileError) { error_log('[Athar evidence preview] '.$fileError->getMessage()); route_error('تعذر فتح ملف الشاهد. تأكد من رفع الملف داخل مجلد storage/uploads وأن صلاحياته قابلة للقراءة.', 404); }
        if (!is_file($filePath) || !is_readable($filePath)) route_error('ملف الدليل غير موجود.', 404);
        header('Content-Type: '.(string)$item['mime_type']);
        header('Content-Disposition: inline; filename*=UTF-8\'\''.rawurlencode((string)$item['original_name']));
        header('Content-Length: '.(string)filesize($filePath));
        header('Cache-Control: private, no-store, max-age=0');
        header('X-Content-Type-Options: nosniff');
        readfile($filePath); exit;
    }
    if ($path === '/profile/avatar') {
        require_user($user); $portfolio=$portfolios->findForUser((int)$user['id']);
        if (!$portfolio || empty($portfolio['avatar_stored_name'])) route_error('الصورة الشخصية غير موجودة.',404);
        try { $filePath=Storage::path((string)$portfolio['avatar_stored_name']); } catch (Throwable $avatarError) { route_error('الصورة الشخصية غير موجودة.',404); }
        header('Content-Type: '.((string)($portfolio['avatar_mime_type']??'image/jpeg'))); header('Content-Disposition: inline'); header('Cache-Control: private, max-age=60'); header('X-Content-Type-Options: nosniff'); readfile($filePath); exit;
    }
    if (preg_match('#^/p/([A-Za-z0-9][A-Za-z0-9-]{2,79})/avatar$#', $path, $match)) {
        $portfolio=$portfolios->findPublic($match[1]);
        if (!$portfolio || (int)($portfolio['show_avatar']??0)!==1 || empty($portfolio['avatar_stored_name'])) route_error('الصورة الشخصية غير متاحة.',404);
        try { $filePath=Storage::path((string)$portfolio['avatar_stored_name']); } catch (Throwable $avatarError) { route_error('الصورة الشخصية غير متاحة.',404); }
        header('Content-Type: '.((string)($portfolio['avatar_mime_type']??'image/jpeg'))); header('Content-Disposition: inline'); header('Cache-Control: public, max-age=300'); header('X-Content-Type-Options: nosniff'); readfile($filePath); exit;
    }
    if (preg_match('#^/p/([A-Za-z0-9][A-Za-z0-9-]{2,79})/evidence/(\d+)$#', $path, $match)) {
        $token=$match[1]; $item=$evidenceRepo->publicForShare((int)$match[2], $token);
        if (!$item || !SubscriptionService::allows($db,(int)$item['user_id'],'public_sharing')) route_error('هذا الشاهد غير متاح للعرض العام.',404);
        if (!empty($item['source_url']) && empty($item['stored_name'])) { header('Referrer-Policy: no-referrer'); header('Location: '.(string)$item['source_url'], true, 302); exit; }
        try { $filePath=Storage::path((string)$item['stored_name']); }
        catch (Throwable $fileError) { error_log('[Athar public evidence] '.$fileError->getMessage()); route_error('ملف الشاهد غير متاح حاليًا. أعد رفعه من محرر ملف الإنجاز ثم حاول مرة أخرى.', 404); }
        if (!is_file($filePath) || !is_readable($filePath)) route_error('ملف الشاهد غير موجود.',404);
        header('Content-Type: '.(string)$item['mime_type']);
        header('Content-Disposition: inline; filename*=UTF-8\'\''.rawurlencode((string)$item['original_name']));
        header('Content-Length: '.(string)filesize($filePath));
        header('Cache-Control: public, max-age=300');
        header('X-Content-Type-Options: nosniff');
        readfile($filePath); exit;
    }
    if (preg_match('#^/p/([A-Za-z0-9][A-Za-z0-9-]{2,79})/section/(\d+)$#', $path, $match)) {
        $token=$match[1]; $portfolio=$portfolios->findPublic($token);
        if ($portfolio) { $disabledTemplate=$db->prepare('SELECT is_enabled FROM template_controls WHERE template_id=:id'); $disabledTemplate->execute(['id'=>(string)($portfolio['template_id']??'')]); $enabledValue=$disabledTemplate->fetchColumn(); if ($enabledValue!==false && (int)$enabledValue!==1) $portfolio['template_id']='classic-sand'; }
        if (!$portfolio) route_error('هذا الرابط غير متاح أو تم إيقافه.',404);
        if (!SubscriptionService::allows($db,(int)$portfolio['user_id'],'public_sharing')) route_error('المشاركة غير متاحة في الخطة الحالية.',404);
        $sectionId=(int)$match[2]; $section=null;
        foreach ($portfolio['sections'] as $candidate) if ((int)$candidate['id']===$sectionId) { $section=$candidate; break; }
        if (!$section) route_error('هذا القسم غير متاح للعرض العام.',404);
        $portfolios->incrementPublicViews((int)$portfolio['id']);
        if (!(int)$portfolio['show_public_name']) $portfolio['headline']='';
        render('section_view',['pageTitle'=>$section['title'],'currentUser'=>null,'portfolio'=>$portfolio,'section'=>$section,'token'=>$token]); exit;
    }
    if (preg_match('#^/p/([A-Za-z0-9][A-Za-z0-9-]{2,79})(?:/(quick|pdf))?$#', $path, $match)) {
        $token=$match[1]; $portfolio=$portfolios->findPublic($token);
        if ($portfolio) { $disabledTemplate=$db->prepare('SELECT is_enabled FROM template_controls WHERE template_id=:id'); $disabledTemplate->execute(['id'=>(string)($portfolio['template_id']??'')]); $enabledValue=$disabledTemplate->fetchColumn(); if ($enabledValue!==false && (int)$enabledValue!==1) $portfolio['template_id']='classic-sand'; }
        if (!$portfolio || !SubscriptionService::allows($db,(int)($portfolio['user_id']??0),'public_sharing') || (($match[2]??'')==='quick' && !(int)$portfolio['quick_share'])) route_error('هذا الرابط غير متاح أو تم إيقافه.',404);
        $portfolios->incrementPublicViews((int)$portfolio['id']);
        if (!(int)$portfolio['show_public_name']) $portfolio['headline']='';
        if (($match[2]??'')==='pdf') redirect('/p/'.$token.'?print=1',302);
        if (!empty($_GET['print'])) { render('print',['pageTitle'=>'نسخة للطباعة','currentUser'=>null,'portfolio'=>$portfolio,'publicMode'=>true]); exit; }
        render('public_portfolio',['pageTitle'=>($match[2]??'')?'نبذة سريعة':'ملف إنجاز مشترك','currentUser'=>null,'portfolio'=>$portfolio,'quick'=>($match[2]??'')==='quick','token'=>$token]); exit;
    }

    if (preg_match('#^/evidence/(\d+)/download$#',$path,$match)) {
        require_user($user); $item=$evidenceRepo->owned((int)$match[1],(int)$user['id']);
        if (!$item) route_error('الدليل غير موجود.',404);
        if (!empty($item['source_url']) && empty($item['stored_name'])) { header('Referrer-Policy: no-referrer'); header('Location: '.(string)$item['source_url'], true, 302); exit; }
        send_download(Storage::path((string)$item['stored_name']),(string)$item['mime_type'],(string)$item['original_name']);
    }

    if (in_array($path,['/privacy-policy','/publishing-policy'],true)) { $key=$path==='/privacy-policy'?'privacy':'publishing'; $stmt=$db->prepare('SELECT * FROM site_policies WHERE policy_key=:key'); $stmt->execute(['key'=>$key]); $policy=$stmt->fetch(); if(!$policy) route_error('السياسة غير موجودة.',404); render('policy',['pageTitle'=>$policy['title'],'currentUser'=>$user,'policy'=>$policy]); exit; }

    if ($path==='/' && $user) redirect('/dashboard',302);
    if ($path==='/') { render('landing',['pageTitle'=>'ملف إنجازك يبدأ من هنا','currentUser'=>null]); exit; }
    if ($path==='/plans') { $plans=$db->query('SELECT * FROM subscription_plans WHERE is_active=1 ORDER BY sort_order,id')->fetchAll(); render('plans',['pageTitle'=>'الخطط والأسعار','currentUser'=>$user,'plans'=>$plans,'currentSubscription'=>$user?SubscriptionService::forUser($db,(int)$user['id']):null]); exit; }
    if ($path==='/login') { if ($user) redirect('/dashboard',302); $blockedUntil=$sessionBan['banned_until']??null; $blockedErrors=$sessionBan?['تم حظر هذا الحساب مؤقتًا.'.(!empty($sessionBan['reason'])?' السبب: '.$sessionBan['reason']:'')]:[]; render('login',['pageTitle'=>'تسجيل الدخول','currentUser'=>null,'errors'=>$blockedErrors,'blockedUntil'=>$blockedUntil,'old'=>[]]); exit; }
    if ($path==='/forgot-password') { if ($user) redirect('/dashboard',302); render('forgot_password',['pageTitle'=>'نسيت كلمة المرور','currentUser'=>null,'email'=>clean_value($_GET['email']??'',190),'sent'=>false,'notice'=>'','errors'=>[]]); exit; }
    if ($path==='/reset-password') { if ($user) redirect('/dashboard',302); render('reset_password',['pageTitle'=>'إعادة تعيين كلمة المرور','currentUser'=>null,'email'=>clean_value($_GET['email']??'',190),'errors'=>[]]); exit; }
    if ($path==='/register') { if ($user) redirect('/dashboard',302); render('register',['pageTitle'=>'إنشاء حساب','currentUser'=>null,'errors'=>[],'old'=>[]]); exit; }

    if ($path==='/dashboard') {
        require_user($user); if (!(int)($user['onboarding_seen']??0)) redirect('/onboarding'); $portfolio=$portfolios->findForUser((int)$user['id']);
        if (!$portfolio) route_error('لم يتم العثور على ملف الإنجاز. أعد تسجيل الدخول.',404);
        $activity=$db->prepare('SELECT event_type,description,created_at FROM activity_log WHERE user_id=:id ORDER BY id DESC LIMIT 8'); $activity->execute(['id'=>$user['id']]);
        render('dashboard',['pageTitle'=>'لوحة التحكم','currentUser'=>$user,'portfolio'=>$portfolio,'completion'=>$portfolios->completion($portfolio),'activity'=>$activity->fetchAll(),'subscription'=>SubscriptionService::forUser($db,(int)$user['id'])]); exit;
    }
    if ($path==='/support') {
        require_user($user); $stmt=$db->prepare('SELECT * FROM support_requests WHERE user_id=:user ORDER BY created_at DESC'); $stmt->execute(['user'=>$user['id']]);
        $reviewStmt=$db->prepare('SELECT * FROM site_reviews WHERE user_id=:user ORDER BY created_at DESC LIMIT 1'); $reviewStmt->execute(['user'=>$user['id']]);
        render('support',['pageTitle'=>'مركز المساعدة','currentUser'=>$user,'portfolio'=>$portfolios->findForUser((int)$user['id']),'requests'=>$stmt->fetchAll(),'latestReview'=>$reviewStmt->fetch()]); exit;
    }
    if ($path==='/owner/support') {
        require_owner($user); $requests=$db->query("SELECT r.*,u.full_name,u.email FROM support_requests r JOIN users u ON u.id=r.user_id ORDER BY CASE r.status WHEN 'new' THEN 0 WHEN 'in_progress' THEN 1 ELSE 2 END,r.created_at DESC")->fetchAll();
        $reviews=$db->query("SELECT r.*,u.full_name,u.email FROM site_reviews r JOIN users u ON u.id=r.user_id ORDER BY r.created_at DESC LIMIT 100")->fetchAll();
        render('owner_support',['pageTitle'=>'بلاغات المساعدة','currentUser'=>$user,'requests'=>$requests,'reviews'=>$reviews,'path'=>$path]); exit;
    }
    $ownerPages=['/owner','/owner/users','/owner/plans','/owner/policies','/owner/security','/owner/settings','/owner/operations','/owner/templates','/owner/visits'];
    if (in_array($path,$ownerPages,true)) {
        require_owner($user);
        if ($path==='/owner') {
            $stats=['security_events'=>(int)$db->query('SELECT COUNT(*) FROM security_events')->fetchColumn(),'inactive'=>(int)$db->query('SELECT COUNT(DISTINCT u.id) FROM users u LEFT JOIN user_bans b ON b.user_id=u.id WHERE u.is_active=0 OR (b.user_id IS NOT NULL AND (b.banned_until IS NULL OR b.banned_until>CURRENT_TIMESTAMP))')->fetchColumn(),'support_new'=>(int)$db->query("SELECT COUNT(*) FROM support_requests WHERE status='new'")->fetchColumn(),'users'=>(int)$db->query('SELECT COUNT(*) FROM users')->fetchColumn(),'shared'=>(int)$db->query('SELECT COUNT(*) FROM portfolios WHERE sharing_enabled=1')->fetchColumn(),'plans'=>(int)$db->query('SELECT COUNT(*) FROM subscription_plans WHERE is_active=1')->fetchColumn()];
            render('owner',['pageTitle'=>'لوحة مالك الموقع','currentUser'=>$user,'stats'=>$stats,'path'=>$path]); exit;
        }
        if ($path==='/owner/users') {
            $users=$db->query("SELECT u.id,u.full_name,u.email,u.role,u.is_owner,u.is_active,u.created_at,p.title,p.sharing_enabled,p.public_view_count,CASE WHEN b.user_id IS NOT NULL AND (b.banned_until IS NULL OR b.banned_until>CURRENT_TIMESTAMP) THEN b.reason ELSE NULL END AS ban_reason,b.banned_until,(SELECT COUNT(*) FROM portfolio_sections s WHERE s.portfolio_id=p.id) AS section_count,(SELECT COUNT(*) FROM evidence e WHERE e.portfolio_id=p.id) AS evidence_count FROM users u LEFT JOIN portfolios p ON p.user_id=u.id LEFT JOIN user_bans b ON b.user_id=u.id ORDER BY u.created_at DESC,u.id DESC")->fetchAll();
            foreach($users as &$managedUser) $managedUser['subscription']=SubscriptionService::forUser($db,(int)$managedUser['id']); unset($managedUser);
            $plans=$db->query('SELECT * FROM subscription_plans ORDER BY sort_order,id')->fetchAll();
            render('owner_users',['pageTitle'=>'المستخدمون والاشتراكات','currentUser'=>$user,'users'=>$users,'plans'=>$plans,'path'=>$path]); exit;
        }
        if ($path==='/owner/plans') { $plans=$db->query('SELECT * FROM subscription_plans ORDER BY sort_order,id')->fetchAll(); render('owner_plans',['pageTitle'=>'الخطط والأسعار','currentUser'=>$user,'plans'=>$plans,'path'=>$path]); exit; }
        if ($path==='/owner/policies') { $rows=$db->query('SELECT * FROM site_policies ORDER BY policy_key')->fetchAll(); $policies=[]; foreach($rows as $row) $policies[$row['policy_key']]=$row; render('owner_policies',['pageTitle'=>'سياسات الموقع','currentUser'=>$user,'policies'=>$policies,'path'=>$path]); exit; }
        if ($path==='/owner/security') { $events=$db->query('SELECT event_type,route,details,created_at FROM security_events ORDER BY id DESC LIMIT 100')->fetchAll(); render('owner_security',['pageTitle'=>'الأحداث الأمنية','currentUser'=>$user,'securityEvents'=>$events,'path'=>$path]); exit; }
        if ($path==='/owner/templates') {
            $controls=$db->query('SELECT template_id,is_enabled,admin_note,updated_at FROM template_controls')->fetchAll(); $controlMap=[]; foreach($controls as $control) $controlMap[$control['template_id']]=$control;
            render('owner_templates',['pageTitle'=>'إدارة القوالب','currentUser'=>$user,'allTemplates'=>TemplateCatalog::all(),'controlMap'=>$controlMap,'path'=>$path]); exit;
        }
        if ($path==='/owner/visits') {
            $visitStats=$db->query("SELECT COUNT(*) AS total,COUNT(DISTINCT v.visitor_ip) AS unique_ips,SUM(CASE WHEN v.is_authenticated=1 THEN 1 ELSE 0 END) AS signed_in,SUM(CASE WHEN v.is_authenticated=0 THEN 1 ELSE 0 END) AS guests FROM visit_log v LEFT JOIN users u ON u.id=v.user_id WHERE v.created_at>=datetime('now','-30 days') AND (v.user_id IS NULL OR COALESCE(u.is_owner,0)=0)")->fetch() ?: [];
            $visits=$db->query("SELECT v.id,v.visitor_ip,v.route,v.user_agent,v.is_authenticated,v.created_at,u.full_name,u.email,u.role FROM visit_log v LEFT JOIN users u ON u.id=v.user_id WHERE v.user_id IS NULL OR COALESCE(u.is_owner,0)=0 ORDER BY v.created_at DESC,v.id DESC LIMIT 250")->fetchAll();
            render('owner_visits',['pageTitle'=>'الزيارات','currentUser'=>$user,'visitStats'=>$visitStats,'visits'=>$visits,'path'=>$path]); exit;
        }
        if ($path==='/owner/operations') {
            $updates=$db->query('SELECT * FROM site_updates ORDER BY created_at DESC,id DESC LIMIT 30')->fetchAll();
            $services=$db->query('SELECT * FROM service_statuses ORDER BY id')->fetchAll();
            render('owner_operations',['pageTitle'=>'التحديثات وحالة الخدمات','currentUser'=>$user,'updates'=>$updates,'services'=>$services,'path'=>$path]); exit;
        }
        if ($path==='/owner/settings') { $siteSettings=SiteSettings::all($db); foreach(['mail_from_email'=>'ATHAR_MAIL_FROM_EMAIL','mail_from_name'=>'ATHAR_MAIL_FROM_NAME','mail_smtp_host'=>'ATHAR_MAIL_SMTP_HOST','mail_smtp_port'=>'ATHAR_MAIL_SMTP_PORT','mail_smtp_encryption'=>'ATHAR_MAIL_SMTP_ENCRYPTION','mail_smtp_username'=>'ATHAR_MAIL_SMTP_USERNAME'] as $setting=>$envKey){if(trim((string)($siteSettings[$setting]??''))===''){ $env=getenv($envKey); if($env!==false)$siteSettings[$setting]=(string)$env; }} $mailPasswordConfigured=(string)($siteSettings['mail_smtp_password']??'')!=='' || (string)(getenv('ATHAR_MAIL_SMTP_PASSWORD')?:'')!==''; render('owner_settings_page',['pageTitle'=>'إعدادات الموقع','currentUser'=>$user,'siteSettings'=>$siteSettings,'mailPasswordConfigured'=>$mailPasswordConfigured,'path'=>$path]); exit; }
    }

    if ($path==='/onboarding') {
        require_user($user);
        if ((int)($user['onboarding_seen']??0) && empty($_GET['replay'])) redirect('/dashboard');
        render('onboarding',['pageTitle'=>'دليل البداية','currentUser'=>$user,'portfolio'=>$portfolios->findForUser((int)$user['id'])]); exit;
    }
    if ($path==='/growth') {
        require_user($user);
        $goalsStmt=$db->prepare('SELECT * FROM portfolio_goals WHERE user_id=:user ORDER BY status ASC,target_date ASC,created_at DESC'); $goalsStmt->execute(['user'=>$user['id']]);
        $refStmt=$db->prepare('SELECT * FROM portfolio_reflections WHERE user_id=:user ORDER BY created_at DESC LIMIT 8'); $refStmt->execute(['user'=>$user['id']]);
        $portfolio=$portfolios->findForUser((int)$user['id']);
        render('growth',['pageTitle'=>'أهدافي وتأملاتي','currentUser'=>$user,'portfolio'=>$portfolio,'goals'=>$goalsStmt->fetchAll(),'reflections'=>$refStmt->fetchAll()]); exit;
    }
    if ($path==='/editor') { require_user($user); $portfolio=$portfolios->findForUser((int)$user['id']); $evidenceQuery=clean_value($_GET['evidence_q']??'',100); if ($evidenceQuery!=='') $portfolio['evidence']=array_values(array_filter($portfolio['evidence']??[],static fn(array $item):bool=>mb_stripos(($item['title']??'').' '.($item['tags']??'').' '.($item['description']??''),$evidenceQuery)!==false)); render('editor',['pageTitle'=>'محرر ملف الإنجاز','currentUser'=>$user,'portfolio'=>$portfolio,'errors'=>[],'evidenceQuery'=>$evidenceQuery]); exit; }
    if ($path==='/profile') { require_user($user); $portfolio=$portfolios->findForUser((int)$user['id']); render('profile',['pageTitle'=>'هويتي التعليمية','currentUser'=>$user,'portfolio'=>$portfolio]); exit; }
    if ($path==='/account/settings') { require_user($user); redirect('/settings#account-settings',302); }
    if ($path==='/settings') {
        require_user($user); $portfolio=$portfolios->findForUser((int)$user['id']); $shareUrl=!empty($portfolio['share_token'])?'/p/'.$portfolio['share_token']:'';
        $newShareUrl=$_SESSION['new_share_url']??''; unset($_SESSION['new_share_url']);
        render('settings',['pageTitle'=>'الخصوصية والمشاركة','currentUser'=>$user,'portfolio'=>$portfolio,'shareUrl'=>$shareUrl,'newShareUrl'=>$newShareUrl]); exit;
    }
    if ($path==='/templates') {
        require_user($user); $portfolio=$portfolios->findForUser((int)$user['id']);
        $filters=['role'=>clean_value($_GET['role']??($user['role']??'student'),20),'category'=>clean_value($_GET['category']??'',40),'palette'=>clean_value($_GET['palette']??'',40),'query'=>clean_value($_GET['q']??'',80),'teacher_subject'=>clean_value($_GET['teacher_subject']??teacher_subject_key((string)($portfolio['subject']??'')),30),'appearance_mode'=>clean_value($_GET['appearance_mode']??($portfolio['appearance_mode']??'light'),10)]; if (!array_key_exists($filters['teacher_subject'],teacher_subject_options())) $filters['teacher_subject']='general'; if (!in_array($filters['appearance_mode'],['light','dark'],true)) $filters['appearance_mode']='light';
        $disabledRows=$db->query('SELECT template_id FROM template_controls WHERE is_enabled=0')->fetchAll(PDO::FETCH_COLUMN); $disabledMap=array_fill_keys(array_map('strval',$disabledRows),true); $activeTemplates=static fn(array $items):array=>array_values(array_filter($items,static fn(array $item):bool=>empty($disabledMap[$item['id']])));
        $visibleTemplates=$activeTemplates(TemplateCatalog::all($filters)); $studentVisible=$activeTemplates(TemplateCatalog::all(['role'=>'student'])); $teacherVisible=$activeTemplates(TemplateCatalog::all(['role'=>'teacher']));
        render('templates',['pageTitle'=>'مكتبة القوالب','currentUser'=>$user,'portfolio'=>$portfolio,'templates'=>$visibleTemplates,'templateCount'=>count($visibleTemplates), 'studentTemplateCount'=>count($studentVisible),'teacherTemplateCount'=>count($teacherVisible),'filters'=>$filters,'customTemplates'=>$portfolios->customTemplates((int)$user['id']),'publicTemplates'=>$portfolios->publicTemplates()]); exit;
    }
    if ($path==='/assistant') { require_user($user); require_subscription_feature($db,(int)$user['id'],'ai'); $portfolio=$portfolios->findForUser((int)$user['id']); render('assistant',['pageTitle'=>'مساعد أثر','currentUser'=>$user,'portfolio'=>$portfolio,'task'=>'improve','inputText'=>'','assistantResult'=>'','assistantError'=>'','assistantProvider'=>'local','ocrText'=>'']); exit; }
    if ($path==='/export/pdf') { require_user($user); redirect('/export?print=1',302); }
    if ($path==='/export') { require_user($user); $portfolio=$portfolios->findForUser((int)$user['id']); render('print',['pageTitle'=>'نسخة للطباعة','currentUser'=>$user,'portfolio'=>$portfolio,'publicMode'=>false]); exit; }

    if ($path==='/backup/json') {
        require_user($user); $portfolio=$portfolios->findForUser((int)$user['id']);
        unset($portfolio['share_token'],$portfolio['email'],$portfolio['user_id']);
        $json=json_encode($portfolio,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);
        header('Content-Type: application/json; charset=UTF-8'); header('Content-Disposition: attachment; filename="athar-backup-'.date('Y-m-d').'.json"'); header('Content-Length: '.strlen($json));
        echo $json; exit;
    }

    if ($path==='/backup/export') {
        require_user($user);
        if (!class_exists(ZipArchive::class)) route_error('إضافة PHP ZIP غير متاحة. استخدم تصدير JSON أو الطباعة إلى PDF.',503);
        $portfolio=$portfolios->findForUser((int)$user['id']); unset($portfolio['share_token']);
        $tmp=tempnam(sys_get_temp_dir(),'athar-'); $zip=new ZipArchive();
        if ($zip->open($tmp,ZipArchive::OVERWRITE)!==true) route_error('تعذر إنشاء النسخة الاحتياطية.');
        $zip->addFromString('portfolio.json',json_encode($portfolio,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR));
        foreach ($portfolio['evidence'] as $item) { try { $file=Storage::path((string)$item['stored_name']); $zip->addFile($file,'files/'.$item['stored_name']); } catch (Throwable) {} }
        $zip->close(); send_download($tmp,'application/zip','athar-backup-'.date('Y-m-d').'.zip',true);
    }

    render('error',['pageTitle'=>'الصفحة غير موجودة','currentUser'=>$user,'errorTitle'=>'هذه الصفحة غير موجودة','errorMessage'=>'قد يكون الرابط غير صحيح أو أن الصفحة نُقلت.','homeLink'=>$user?'/dashboard':'/'],404);
} catch (Throwable $exception) {
    error_log('[Athar] ' . get_class($exception) . ': ' . $exception->getMessage());
    if (!headers_sent()) http_response_code(500);
    echo '<!doctype html><html lang="ar" dir="rtl"><meta charset="utf-8"><meta name="viewport" content="width=device-width"><title>تعذر تشغيل أثر</title><body style="font-family:system-ui,Tahoma,sans-serif;background:#f7f4ec;color:#17324d;padding:4rem;max-width:48rem;margin:auto"><h1>تعذر إكمال الطلب</h1><p>لم يُحفظ أي تعديل غير مكتمل. راجع إعداد PHP وSQLite ثم أعد المحاولة.</p><a href="/">العودة إلى الصفحة الرئيسية</a></body></html>';
}

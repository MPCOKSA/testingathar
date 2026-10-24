<?php
declare(strict_types=1);

final class Storage
{
    private const MIME_EXTENSIONS = [
        'application/pdf'=>'pdf', 'image/jpeg'=>'jpg', 'image/png'=>'png', 'image/webp'=>'webp',
        'image/gif'=>'gif', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'=>'docx',
        'application/msword'=>'doc', 'application/vnd.openxmlformats-officedocument.presentationml.presentation'=>'pptx',
        'application/vnd.ms-powerpoint'=>'ppt', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'=>'xlsx',
        'application/vnd.ms-excel'=>'xls', 'text/plain'=>'txt',
    ];

    public static function directory(): string
    {
        $configured=trim((string)(getenv('ATHAR_UPLOAD_DIR') ?: ''));
        if ($configured==='') return dirname(__DIR__) . '/storage/uploads';
        return str_starts_with($configured,'/') ? rtrim($configured,'/') : dirname(__DIR__) . '/' . ltrim($configured,'/');
    }

    public static function maxBytes(): int
    {
        $mb = filter_var(getenv('ATHAR_UPLOAD_MAX_MB') ?: '6', FILTER_VALIDATE_INT);
        return max(1, min(6, (int)($mb ?: 6))) * 1024 * 1024;
    }

    public static function save(array $file): array
    {
        $error=(int)($file['error'] ?? UPLOAD_ERR_NO_FILE);
        if ($error !== UPLOAD_ERR_OK) {
            $messages=[
                UPLOAD_ERR_INI_SIZE=>'حجم الملف أكبر من الحد الذي تسمح به الاستضافة.',
                UPLOAD_ERR_FORM_SIZE=>'حجم الملف أكبر من الحد المسموح في النموذج.',
                UPLOAD_ERR_PARTIAL=>'انقطع رفع الملف قبل اكتماله. أعد المحاولة أو استخدم خيار الرابط.',
                UPLOAD_ERR_NO_FILE=>'اختر ملفًا أو استخدم حقل إضافة الشاهد من رابط.',
                UPLOAD_ERR_NO_TMP_DIR=>'تعذر على الاستضافة إنشاء مجلد مؤقت للرفع.',
                UPLOAD_ERR_CANT_WRITE=>'رفضت الاستضافة كتابة الملف. استخدم خيار الرابط أو راجع صلاحيات storage/uploads.',
                UPLOAD_ERR_EXTENSION=>'أوقفت الاستضافة رفع الملف بإضافة PHP. استخدم خيار الرابط أو نوع ملف آخر.',
            ];
            throw new RuntimeException($messages[$error]??'تعذر رفع الملف (رمز الخطأ '.$error.'). استخدم خيار الرابط إذا استمر الخطأ.');
        }
        $size = (int)($file['size'] ?? 0);
        if ($size < 1 || $size > self::maxBytes()) throw new RuntimeException('حجم الملف غير مسموح. الحد الحالي ' . (int)(self::maxBytes()/1024/1024) . ' ميغابايت.');
        $tmp = $file['tmp_name'] ?? '';
        if (!is_uploaded_file($tmp)) throw new RuntimeException('لم يتم التحقق من مصدر الملف المرفوع.');
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($tmp) ?: '';
        if (!isset(self::MIME_EXTENSIONS[$mime])) throw new RuntimeException('نوع الملف غير مدعوم. ارفع صورة أو PDF أو مستند Office أو ملفًا نصيًا.');
        $dir = self::directory();
        if (!is_dir($dir) && !mkdir($dir, 0700, true) && !is_dir($dir)) throw new RuntimeException('تعذر إنشاء مساحة التخزين الخاصة.');
        $realDir=realpath($dir); if ($realDir===false || is_link($dir)) throw new RuntimeException('مسار التخزين غير آمن.');
        $stored = bin2hex(random_bytes(24)) . '.' . self::MIME_EXTENSIONS[$mime];
        $destination=$realDir . DIRECTORY_SEPARATOR . $stored;
        if (!move_uploaded_file($tmp, $destination)) throw new RuntimeException('تعذر حفظ الملف.');
        @chmod($destination, 0600);
        $original = basename((string)($file['name'] ?? 'ملف'));
        $original = preg_replace('/[\x00-\x1F\x7F]/u', '', $original) ?: 'ملف';
        return ['stored_name'=>$stored,'original_name'=>mb_substr($original,0,180),'mime_type'=>$mime,'file_size'=>$size];
    }

    public static function saveFromUrl(string $url, string $sourceKind = 'url'): array
    {
        $parts=parse_url($url); $host=strtolower((string)($parts['host']??''));
        if ($sourceKind==='drive' && preg_match('~/file/d/([A-Za-z0-9_-]+)~',$url,$match)) {
            $url='https://drive.usercontent.google.com/download?id='.rawurlencode($match[1]).'&export=download&confirm=t';
        }
        $tmp=tempnam(sys_get_temp_dir(),'athar-link-');
        if ($tmp===false) throw new RuntimeException('تعذر إنشاء ملف مؤقت لاستيراد الشاهد من الرابط.');
        try {
            $context=stream_context_create(['http'=>['timeout'=>30,'follow_location'=>1,'max_redirects'=>5,'user_agent'=>'Athar Evidence Importer/1.0'],'ssl'=>['verify_peer'=>true,'verify_peer_name'=>true]]);
            $input=@fopen($url,'rb',false,$context);
            if (!$input) throw new RuntimeException('تعذر تنزيل الشاهد من الرابط. تأكد أن الرابط متاح ويمكن فتحه دون تسجيل دخول.');
            $output=@fopen($tmp,'wb'); if (!$output) throw new RuntimeException('تعذر تجهيز مساحة مؤقتة لاستيراد الشاهد.');
            $copied=stream_copy_to_stream($input,$output,self::maxBytes()+1); fclose($input); fclose($output);
            if ($copied===false || $copied<1) throw new RuntimeException('الرابط لم يُرجع ملفًا صالحًا.');
            if ($copied>self::maxBytes()) throw new RuntimeException('حجم الشاهد من الرابط يتجاوز الحد الحالي '.(int)(self::maxBytes()/1024/1024).' ميغابايت.');
            $finfo=new finfo(FILEINFO_MIME_TYPE); $mime=$finfo->file($tmp)?:'';
            if (!isset(self::MIME_EXTENSIONS[$mime])) throw new RuntimeException('الرابط لا يحتوي على صورة أو PDF أو مستند Office أو ملف نصي مدعوم.');
            $dir=self::directory(); if (!is_dir($dir) && !mkdir($dir,0700,true) && !is_dir($dir)) throw new RuntimeException('تعذر إنشاء مساحة التخزين الخاصة.');
            $realDir=realpath($dir); if ($realDir===false || is_link($dir)) throw new RuntimeException('مسار التخزين غير آمن.');
            $stored=bin2hex(random_bytes(24)).'.'.self::MIME_EXTENSIONS[$mime];
            if (!rename($tmp,$realDir.DIRECTORY_SEPARATOR.$stored)) throw new RuntimeException('تعذر حفظ الشاهد المستورد.');
            @chmod($realDir.DIRECTORY_SEPARATOR.$stored,0600);
            $name=basename((string)($parts['path']??'')); $name=$name!==''?$name:($sourceKind==='drive'?'Google Drive evidence':'Linked evidence');
            return ['stored_name'=>$stored,'original_name'=>mb_substr($name,0,180),'mime_type'=>$mime,'file_size'=>(int)$copied];
        } finally { if (is_file($tmp)) @unlink($tmp); }
    }

    public static function path(string $storedName): string
    {
        if ($storedName==='' || !preg_match('/^[A-Za-z0-9][A-Za-z0-9._-]{0,180}$/', basename($storedName)) || str_contains($storedName,'..')) throw new RuntimeException('اسم تخزين غير صالح.');
        foreach (array_unique([self::directory(),dirname(__DIR__).'/storage',dirname(__DIR__).'/storage/files']) as $directory) { $dir=realpath($directory); $path=$directory.'/'.basename($storedName); if ($dir!==false && !is_link($path) && is_file($path)) { $real=realpath($path); if ($real!==false && dirname($real)===$dir && is_readable($real)) return $real; } }
        throw new RuntimeException('الملف غير موجود.');
    }

    public static function delete(string $storedName): void
    {
        if (preg_match('/^[a-f0-9]{48}\.[a-z0-9]{1,8}$/', $storedName)) {
            $path = self::directory() . '/' . $storedName;
            if (is_file($path) && !is_link($path)) @unlink($path);
        }
    }
}

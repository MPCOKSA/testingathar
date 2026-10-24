<?php
declare(strict_types=1);

final class TemplateCatalog
{
    private const BASES = [
        ['key'=>'classic','name'=>'أكاديمي كلاسيكي','category'=>'أكاديمي','layout'=>'classic','role'=>'teacher'],
        ['key'=>'editorial','name'=>'تحريري أنيق','category'=>'رسمي','layout'=>'editorial','role'=>'teacher'],
        ['key'=>'timeline','name'=>'رحلة الإنجاز','category'=>'إبداعي','layout'=>'timeline','role'=>'student'],
        ['key'=>'gallery','name'=>'معرض الأعمال','category'=>'إبداعي','layout'=>'gallery','role'=>'student'],
        ['key'=>'minimal','name'=>'بساطة واضحة','category'=>'بسيط','layout'=>'minimal','role'=>'student'],
        ['key'=>'report','name'=>'تقرير مهني','category'=>'رسمي','layout'=>'report','role'=>'teacher'],
        ['key'=>'student','name'=>'رحلة المتعلم','category'=>'طالب','layout'=>'student','role'=>'student'],
        ['key'=>'folio','name'=>'دفتر الأثر','category'=>'أكاديمي','layout'=>'folio','role'=>'student'],
    ];
    private const PALETTES = [
        ['id'=>'sand','name'=>'رملي فاخر','primary'=>'#17324d','accent'=>'#c18a35','surface'=>'#fbf7ef'],
        ['id'=>'sage','name'=>'أخضر هادئ','primary'=>'#24443e','accent'=>'#7e9b79','surface'=>'#f4f7f1'],
        ['id'=>'azure','name'=>'أزرق تعليمي','primary'=>'#183d68','accent'=>'#4c88bd','surface'=>'#f2f7fb'],
        ['id'=>'plum','name'=>'برقوقي ناعم','primary'=>'#49314d','accent'=>'#b07b91','surface'=>'#faf4f7'],
        ['id'=>'clay','name'=>'طيني دافئ','primary'=>'#593d34','accent'=>'#c77a55','surface'=>'#fbf4ef'],
    ];
    private const MODERN = [
        ['id'=>'modern-06','name'=>'تقرير بصري','category'=>'مودرن','layout'=>'report','role'=>'teacher','palette'=>['id'=>'modern-06','name'=>'أخضر زمردي','primary'=>'#123b36','accent'=>'#36c49a','surface'=>'#f0fbf6']],
        ['id'=>'modern-07','name'=>'مدار','category'=>'مودرن','layout'=>'classic','role'=>'teacher','palette'=>['id'=>'modern-07','name'=>'تركواز','primary'=>'#123b43','accent'=>'#2dd4bf','surface'=>'#effcfb']],
        ['id'=>'modern-08','name'=>'استوديو','category'=>'مودرن','layout'=>'gallery','role'=>'teacher','palette'=>['id'=>'modern-08','name'=>'برتقالي محروق','primary'=>'#472016','accent'=>'#f97316','surface'=>'#fff7ed']],
        ['id'=>'modern-09','name'=>'واجهة هادئة','category'=>'مودرن','layout'=>'minimal','role'=>'teacher','palette'=>['id'=>'modern-09','name'=>'رمادي أزرق','primary'=>'#26364a','accent'=>'#94a3b8','surface'=>'#f8fafc']],
        ['id'=>'modern-10','name'=>'بوصلة','category'=>'مودرن','layout'=>'timeline','role'=>'student','palette'=>['id'=>'modern-10','name'=>'أزرق سماوي','primary'=>'#123b68','accent'=>'#60a5fa','surface'=>'#f1f7ff']],
        ['id'=>'modern-11','name'=>'موجز قيادي','category'=>'مودرن','layout'=>'report','role'=>'teacher','palette'=>['id'=>'modern-11','name'=>'أحمر مرجاني','primary'=>'#4b1d2a','accent'=>'#fb7185','surface'=>'#fff4f5']],
        ['id'=>'modern-12','name'=>'بطاقات','category'=>'مودرن','layout'=>'gallery','role'=>'teacher','palette'=>['id'=>'modern-12','name'=>'أخضر زيتوني','primary'=>'#30452c','accent'=>'#a3c76d','surface'=>'#f6faef']],
        ['id'=>'modern-13','name'=>'نقطة بداية','category'=>'مودرن','layout'=>'classic','role'=>'student','palette'=>['id'=>'modern-13','name'=>'وردي أزرق','primary'=>'#263b63','accent'=>'#f0abfc','surface'=>'#fff7ff']],
        ['id'=>'modern-14','name'=>'معمل الأفكار','category'=>'مودرن','layout'=>'editorial','role'=>'teacher','palette'=>['id'=>'modern-14','name'=>'أزرق وحمضي','primary'=>'#1e293b','accent'=>'#d9f99d','surface'=>'#f8faf3']],
        ['id'=>'modern-15','name'=>'صفحاتي','category'=>'مودرن','layout'=>'folio','role'=>'student','palette'=>['id'=>'modern-15','name'=>'كريمي وبني','primary'=>'#493a2c','accent'=>'#d6a66a','surface'=>'#fffaf0']],
        ['id'=>'modern-16','name'=>'أثر واضح','category'=>'مودرن','layout'=>'minimal','role'=>'teacher','palette'=>['id'=>'modern-16','name'=>'أزرق ليلي','primary'=>'#111827','accent'=>'#818cf8','surface'=>'#f5f7ff']],
        ['id'=>'modern-17','name'=>'مشهد كامل','category'=>'مودرن','layout'=>'gallery','role'=>'student','palette'=>['id'=>'modern-17','name'=>'أخضر مائي','primary'=>'#164e63','accent'=>'#67e8f9','surface'=>'#f0fdff']],
        ['id'=>'modern-18','name'=>'أطلس','category'=>'مودرن','layout'=>'timeline','role'=>'teacher','palette'=>['id'=>'modern-18','name'=>'ذهبي داكن','primary'=>'#3b2f20','accent'=>'#eab308','surface'=>'#fffcf0']],
        ['id'=>'modern-19','name'=>'نموذج المستقبل','category'=>'مودرن','layout'=>'editorial','role'=>'student','palette'=>['id'=>'modern-19','name'=>'بنفسجي وفيروزي','primary'=>'#312e81','accent'=>'#2dd4bf','surface'=>'#f5f3ff']],
        ['id'=>'modern-20','name'=>'نبض الإنجاز','category'=>'مودرن','layout'=>'contrast','role'=>'student','palette'=>['id'=>'modern-20','name'=>'أزرق وفوشيا','primary'=>'#172554','accent'=>'#f472b6','surface'=>'#fdf4ff']],
        ['id'=>'modern-21','name'=>'سحابة المعرفة','category'=>'مودرن','layout'=>'minimal','role'=>'student','palette'=>['id'=>'modern-21','name'=>'فيروزي ومرجاني','primary'=>'#134e4a','accent'=>'#fb7185','surface'=>'#f0fdfa']],
        ['id'=>'modern-22','name'=>'مختبر المستقبل','category'=>'مودرن','layout'=>'gallery','role'=>'student','palette'=>['id'=>'modern-22','name'=>'أخضر نيون','primary'=>'#183b2b','accent'=>'#bef264','surface'=>'#f7fee7']],
        ['id'=>'modern-23','name'=>'بصمة رقمية','category'=>'مودرن','layout'=>'editorial','role'=>'student','palette'=>['id'=>'modern-23','name'=>'أزرق ليلي وذهبي','primary'=>'#172554','accent'=>'#fbbf24','surface'=>'#eff6ff']],
        ['id'=>'modern-24','name'=>'مدرج الطموح','category'=>'مودرن','layout'=>'timeline','role'=>'student','palette'=>['id'=>'modern-24','name'=>'بنفسجي ومرجاني','primary'=>'#4c1d95','accent'=>'#fb7185','surface'=>'#faf5ff']],
        ['id'=>'modern-25','name'=>'أثر مشترك','category'=>'مودرن','layout'=>'folio','role'=>'both','palette'=>['id'=>'modern-25','name'=>'أسود وأبيض لامع','primary'=>'#18181b','accent'=>'#facc15','surface'=>'#fafafa']],
    ];

    private static function definitions(): array
    {
        $path=dirname(__DIR__).'/config/templates.json';
        if (is_file($path)) {
            $data=json_decode((string)file_get_contents($path),true);
            if (is_array($data) && count($data['bases']??[])===8 && count($data['palettes']??[])===5) return $data;
        }
        return ['bases'=>self::BASES,'palettes'=>self::PALETTES];
    }

    public static function all(array $filters = []): array
    {
        $all=[]; $definitions=self::definitions();
        foreach ($definitions['bases'] as $base) foreach ($definitions['palettes'] as $palette) {
            $id=$base['key'].'-'.$palette['id'];
            $item=['id'=>$id,'name'=>$base['name'].' · '.$palette['name'],'category'=>$base['category'],'layout'=>$base['layout'],'palette'=>$palette,'role'=>$base['role']];
            if (isset($filters['category']) && $filters['category']!=='' && $filters['category']!==$item['category']) continue;
            if (isset($filters['palette']) && $filters['palette']!=='' && $filters['palette']!==$item['palette']['id']) continue;
            if (isset($filters['query']) && $filters['query']!=='' && mb_stripos($item['name'].' '.$item['category'], $filters['query'])===false) continue;
            if (isset($filters['role']) && $filters['role']==='student' && $item['role']!=='student') continue;
            if (isset($filters['role']) && $filters['role']==='teacher' && $item['role']!=='teacher') continue;
            $all[]=$item;
        }
        foreach (self::MODERN as $item) {
            if (isset($filters['category']) && $filters['category']!=='' && $filters['category']!==$item['category']) continue;
            if (isset($filters['query']) && $filters['query']!=='' && mb_stripos($item['name'].' '.$item['category'],$filters['query'])===false) continue;
            if (isset($filters['role']) && $filters['role']!=='' && $filters['role']!==$item['role']) continue;
            $all[]=$item;
        }
        $experienceNames=['مدار','مكعبات','طبقات زجاجية','متحف الإنجاز','مدينة معرفية','خط زمني فضائي','لوحة إنجاز تفاعلية','ملف سينمائي','شبكة عائمة','مخطط المستقبل','بوصلة ثلاثية','كون الإنجاز','ممر الضوء','أرشيف نبض','مجرة المهارات','استوديو الحركة','حديقة الأفكار','مسرح الأثر','كريستال التعلم','سلم الطموح','نوافذ المعرفة','قوس الإنجاز','مكعبات التفكير','مختبر الإبداع','رحلة البكسل','بهو الشهادات','أطياف النمو','مركز التحكم','جسر التقدم','خريطة الأثر','موجة التعلم','برج الإنجاز','حديقة المشاريع','دائرة الشغف','بلاطات النجاح','بوابة المعرفة','معرض اللحظات','متاهة المهارات','مسرح المستقبل','رادار التقدم','غرفة العمليات','موجز بصري','أرشيف الضوء','سحابة الأفكار','ميدان الإنجاز','خط نبض','مجرة الأعمال','متحف التجربة','مسار النجوم','شبكة الأثر','منصة القمم','مرصد التعلم','مكعبات القصة','واجهة الرحلة','مختبر النتائج','عالم الشواهد','سينما الإنجاز','أطلس المهارات','موجة المستقبل'];
        $families=['orbit','cubes','glass','museum','city','cosmic','board','cinema','floating','blueprint','prism','constellation'];
        $teacherExperiences=['orbit'=>'استوديو القيادة','cubes'=>'مكعبات المنهج','glass'=>'مختبر الممارسات','museum'=>'متحف الخبرة','city'=>'مدينة التعلم','cosmic'=>'مرصد الأثر','board'=>'لوحة التخطيط','cinema'=>'فيلم الرحلة المهنية','floating'=>'شبكة العلاقات','blueprint'=>'مخطط الدرس','prism'=>'منشور الابتكار','constellation'=>'كوكبة الإنجاز'];
        foreach ($all as $index => &$entry) { $entry['variant']='scene-'.str_pad((string)($index+1),2,'0',STR_PAD_LEFT); $entry['experience']=$experienceNames[$index]??('تجربة '.$index); $entry['family']=$families[$index%count($families)]; if ($entry['role']==='teacher') $entry['experience']=$teacherExperiences[$entry['family']]??$entry['experience']; }
        unset($entry);
        if (($filters['role'] ?? '') === 'student') $all = array_slice($all, 0, 30);
        if (($filters['role'] ?? '') === 'teacher') $all = array_slice($all, 0, 29);
        return $all;
    }

    public static function find(string $id): ?array
    {
        foreach (self::all() as $item) if ($item['id']===$id) return $item;
        return null;
    }

    public static function palettes(): array
    {
        return self::definitions()['palettes'];
    }

    public static function fonts(): array
    {
        return ['IBM Plex Sans Arabic','Cairo','Tajawal','Noto Kufi Arabic','Noto Sans Arabic','Almarai','Changa','El Messiri','Readex Pro','Rubik','Alexandria','Lemonada','Mada','Amiri','Aref Ruqaa Ink','Marhey'];
    }
}

<?php
declare(strict_types=1);

final class PortfolioRepository
{
    private const STARTER_SECTIONS = [
        'teacher' => [
            'أداء الواجبات الوظيفية' => ['سجل الدوام الرسمي','سجل المناوبة والإشراف','سجل الانتظار','خطة توزيع المنهج'],
            'التفاعل مع المجتمع المحلي' => ['سجل مجتمعات التعلم','سجل تبادل الزيارات','تقرير درس تطبيقي','شهادات حضور'],
            'التفاعل مع أولياء الأمور' => ['صور من الجمعية العمومية','تقرير اجتماع مع ولي الأمر','نسخة من الخطة الأسبوعية'],
            'التنوع في استراتيجيات التدريس' => ['تقرير عن تطبيق استراتيجية','ملف إنجاز المعلم'],
            'تحسين نتائج المتعلمين' => ['نتائج الاختبارات القبلية والبعدية','كشف متابعة الطلاب'],
            'إعداد وتنفيذ خطة التعلم' => ['خطة توزيع المنهج','نماذج من إعداد الدروس','نماذج من الواجبات والاختبارات'],
            'توظيف تقنيات ووسائل التعلم المناسبة' => ['صور من الوسائل التعليمية','تقرير عن برنامج تقني'],
            'تهيئة البيئة التعليمية' => ['تقرير تصنيف الطلاب حسب أنماط التعلم','نماذج من التحفيز المادي والمعنوي'],
            'الإدارة الصفية' => ['كشف المتابعة','تطبيق إدارة الصف'],
            'تحليل نتائج المتعلمين وتشخيص مستوياتهم' => ['تقرير تحليل نتائج الطلاب','سجل معالجة الفاقد التعليمي'],
            'تنوع أساليب التقويم' => ['نماذج من الاختبارات','ملفات إنجاز الطلاب','نماذج من المهام الأدائية والمشاريع'],
        ],
        'student' => [
            'نبذتي التعليمية' => ['نبذة تعريفية','اهتماماتي التعليمية'],
            'أهدافي وخطة تعلمي' => ['أهدافي الفصلية','خطة متابعة التقدم'],
            'أعمالي ومشروعاتي' => ['مشروعاتي','نماذج من أعمالي'],
            'ما تعلمته وتأملاتي' => ['تأملاتي','دروس تعلمتها'],
            'مهاراتي وإنجازاتي' => ['التفكير الناقد وحل المشكلات','مهاراتي','شهادات وإنجازات'],
            'تغذية راجعة' => ['ملاحظات المعلم','خطوات التحسين'],
            'سجل تطوري' => ['متابعة التقدم','أهدافي القادمة'],
        ],
    ];
    public function __construct(private readonly PDO $db)
    {
    }

    public static function teacherWitnessTypes(): array { return self::STARTER_SECTIONS['teacher']; }

    public static function publicPresentationOptions(): array
    {
        return [
            'landing'=>'واجهة الغلاف',
            'cards'=>'شبكة البطاقات',
            'pages'=>'كتاب الصفحات',
            'editorial'=>'مجلة تربوية',
            'timeline'=>'رحلة زمنية',
            'mosaic'=>'فسيفساء الشواهد',
            'cinema'=>'عرض سينمائي',
            'dashboard'=>'لوحة مؤشرات',
            'story'=>'قصة أثر',
            'studio'=>'استوديو إبداعي',
            'minimal'=>'بساطة راقية',
            'atlas'=>'أطلس المعرفة',
            'glass'=>'زجاج ثلاثي الأبعاد',
            'orbit'=>'مدار ثلاثي الأبعاد',
            'museum'=>'معرض الشواهد',
            'journal'=>'دفتر الإنجاز',
            'steps'=>'محطات متتابعة',
            'split'=>'عمودان متوازنان',
            'stack'=>'بطاقات متراكبة',
            'polaroid'=>'صور فورية',
            'blueprint'=>'مخطط هندسي',
            'paper'=>'ورق أنيق',
            'neon'=>'وهج رقمي',
            'nature'=>'طبيعة هادئة',
            'aurora'=>'شفق متدرج',
            'bento'=>'شبكة بينتو',
            'classroom'=>'لوحة تعليمية',
            'storyboard'=>'لوحة قصصية',
            'spotlight'=>'تركيز على الإنجاز',
        ];
    }

    public function createForUser(int $userId, string $role): void
    {
        $statement = $this->db->prepare(
            'INSERT INTO portfolios (user_id, portfolio_type, title)
             VALUES (:user_id, :type, :title)'
        );
        $statement->execute([
            'user_id' => $userId,
            'type' => $role,
            'title' => $role === 'teacher' ? 'ملفي المهني' : 'ملفي الدراسي',
        ]);
        $portfolioId = (int) $this->db->lastInsertId();

        $section = $this->db->prepare(
            'INSERT INTO portfolio_sections (portfolio_id, title, parent_id, sort_order)
             VALUES (:portfolio_id, :title, :parent_id, :sort_order)'
        );
        $sort = 0;
        foreach (self::STARTER_SECTIONS[$role] as $title => $children) {
            $section->execute(['portfolio_id'=>$portfolioId,'title'=>$title,'parent_id'=>null,'sort_order'=>$sort++]);
            $parentId=(int)$this->db->lastInsertId();
            if ($role==='teacher') continue;
            foreach ($children as $childTitle) {
                $section->execute(['portfolio_id'=>$portfolioId,'title'=>$childTitle,'parent_id'=>$parentId,'sort_order'=>$sort++]);
            }
        }
    }

    public function findForUser(int $userId): ?array
    {
        $statement = $this->db->prepare('SELECT p.*,u.full_name,u.role,pr.headline,pr.school_name,pr.grade_level,pr.subject,pr.avatar_stored_name,pr.avatar_mime_type,pr.show_avatar FROM portfolios p JOIN users u ON u.id=p.user_id LEFT JOIN profiles pr ON pr.user_id=u.id WHERE p.user_id=:user_id LIMIT 1');
        $statement->execute(['user_id' => $userId]);
        $portfolio = $statement->fetch();
        if (!$portfolio) return null;
        $portfolio['sections'] = $this->sections((int)$portfolio['id']);
        $portfolio['evidence'] = $this->evidenceForPortfolio((int)$portfolio['id']);
        return $portfolio;
    }

    public function sections(int $portfolioId, bool $publicOnly = false): array
    {
        $sql='SELECT * FROM portfolio_sections WHERE portfolio_id=:id';
        if ($publicOnly) $sql .= " AND visibility='public' AND enabled=1";
        $sql .= ' ORDER BY sort_order,id';
        $stmt=$this->db->prepare($sql); $stmt->execute(['id'=>$portfolioId]); return $stmt->fetchAll();
    }

    public function evidenceForPortfolio(int $portfolioId): array
    {
        $stmt=$this->db->prepare('SELECT e.*,s.title AS section_title FROM evidence e LEFT JOIN portfolio_sections s ON s.id=e.section_id WHERE e.portfolio_id=:id ORDER BY e.sort_order ASC,e.created_at DESC,e.id DESC');
        $stmt->execute(['id'=>$portfolioId]); return $stmt->fetchAll();
    }

    public function findPublic(string $token): ?array
    {
        $stmt=$this->db->prepare('SELECT p.*,u.full_name,u.role,pr.headline,pr.school_name,pr.grade_level,pr.subject,pr.avatar_stored_name,pr.avatar_mime_type,pr.show_avatar FROM portfolios p JOIN users u ON u.id=p.user_id LEFT JOIN profiles pr ON pr.user_id=u.id WHERE p.share_token=:token AND p.sharing_enabled=1 LIMIT 1');
        $stmt->execute(['token'=>$token]); $portfolio=$stmt->fetch();
        if (!$portfolio) return null;
        $portfolio['sections']=$this->sections((int)$portfolio['id'],true);
        $visibleIds=array_map('intval',array_column($portfolio['sections'],'id'));
        $portfolio['sections']=array_values(array_filter($portfolio['sections'],static fn(array $section):bool=>empty($section['parent_id']) || in_array((int)$section['parent_id'],$visibleIds,true)));
        $visibleIds=array_map('intval',array_column($portfolio['sections'],'id'));
        $all=$this->evidenceForPortfolio((int)$portfolio['id']);
        $portfolio['evidence']=array_values(array_filter($all,static fn(array $item):bool=>$item['visibility']==='public' && $item['section_id']!==null && in_array((int)$item['section_id'],$visibleIds,true)));
        return $portfolio;
    }

    public function saveForUser(
        int $userId,
        string $title,
        string $introduction,
        array $sections
    ): void {
        $portfolio = $this->db->prepare(
            'SELECT id,portfolio_type FROM portfolios WHERE user_id = :user_id LIMIT 1'
        );
        $portfolio->execute(['user_id' => $userId]);
        $portfolioRow = $portfolio->fetch(PDO::FETCH_ASSOC);
        $portfolioId = (int)($portfolioRow['id']??0);
        $isTeacher = ($portfolioRow['portfolio_type']??'')==='teacher';

        if (!$portfolioId) {
            throw new RuntimeException('The portfolio could not be found.');
        }

        $this->db->beginTransaction();
        try {
            $update = $this->db->prepare(
                'UPDATE portfolios
                 SET title = :title, introduction = :introduction, updated_at = CURRENT_TIMESTAMP
                 WHERE id = :id AND user_id = :user_id'
            );
            $update->execute([
                'title' => $title,
                'introduction' => $introduction,
                'id' => $portfolioId,
                'user_id' => $userId,
            ]);

            $existingStmt=$this->db->prepare('SELECT id FROM portfolio_sections WHERE portfolio_id=:id');
            $existingStmt->execute(['id'=>$portfolioId]);
            $existing=array_map('intval',array_column($existingStmt->fetchAll(),'id'));
            $seen=[];
            $clientIds=[]; $pendingParents=[];
            $updateSection=$this->db->prepare('UPDATE portfolio_sections SET title=:title,body=:body,visibility=:visibility,enabled=:enabled,parent_id=:parent_id,display_mode=:display_mode,subject_theme=:subject_theme,sort_order=:sort,updated_at=CURRENT_TIMESTAMP WHERE id=:id AND portfolio_id=:portfolio');
            // يجب أن يطابق عدد القيم عدد الأعمدة؛ إغفال subject_theme كان يجعل SQLite
            // يرفض كل عملية حفظ عند إنشاء قسم جديد أو إضافة مادة.
            $insertSection=$this->db->prepare('INSERT INTO portfolio_sections (portfolio_id,title,body,visibility,enabled,parent_id,display_mode,subject_theme,sort_order,updated_at) VALUES (:portfolio,:title,:body,:visibility,:enabled,:parent_id,:display_mode,:subject_theme,:sort,CURRENT_TIMESTAMP)');
            foreach (array_values($sections) as $index=>$section) {
                $id=(int)($section['id']??0);
                // دعم النسختين القديمة والجديدة من نموذج المحرر.
                $parentRef=$isTeacher?'0':(string)($section['parent_ref']??($section['parent_id']??'0'));
                $parent=(ctype_digit($parentRef)?(int)$parentRef:0); if ($parent===$id || !in_array($parent,$existing,true)) $parent=0;
                $mode=in_array(($section['display_mode']??'auto'),['auto','page','compact'],true)?$section['display_mode']:'auto';
                $data=['title'=>$section['title'],'body'=>$section['body'],'visibility'=>$section['visibility']??'private','enabled'=>(int)($section['enabled']??1),'parent_id'=>$parent?:null,'display_mode'=>$mode,'subject_theme'=>array_key_exists((string)($section['subject_theme']??''),subject_theme_options())?(string)$section['subject_theme']:'general','sort'=>$index];
                if ($id>0 && in_array($id,$existing,true)) { $updateSection->execute($data+['id'=>$id,'portfolio'=>$portfolioId]); $seen[]=$id; $savedId=$id; }
                else { $insertSection->execute(['portfolio'=>$portfolioId]+$data); $savedId=(int)$this->db->lastInsertId(); }
                $clientKey=(string)($section['client_key']??''); if ($clientKey!=='') $clientIds[$clientKey]=$savedId;
                if (!ctype_digit($parentRef) && $parentRef!=='0') $pendingParents[$savedId]=$parentRef;
            }
            if ($pendingParents) { $resolve=$this->db->prepare('UPDATE portfolio_sections SET parent_id=:parent WHERE id=:id AND portfolio_id=:portfolio'); foreach ($pendingParents as $childId=>$parentKey) if (isset($clientIds[$parentKey]) && $clientIds[$parentKey]!==$childId) $resolve->execute(['parent'=>$clientIds[$parentKey],'id'=>$childId,'portfolio'=>$portfolioId]); }
            $removed=array_diff($existing,$seen);
            if ($removed) {
                $marks=implode(',',array_fill(0,count($removed),'?'));
                $delete=$this->db->prepare("DELETE FROM portfolio_sections WHERE portfolio_id=? AND id IN ($marks)");
                $delete->execute(array_merge([$portfolioId],array_values($removed)));
            }

            $this->db->commit();
        } catch (Throwable $error) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $error;
        }
    }

    public function saveProfile(int $userId, array $data): void
    {
        $stmt=$this->db->prepare('UPDATE profiles SET headline=:headline,school_name=:school,grade_level=:grade,subject=:subject,avatar_stored_name=:avatar,avatar_mime_type=:mime,show_avatar=:show_avatar,updated_at=CURRENT_TIMESTAMP WHERE user_id=:id');
        $stmt->execute(['headline'=>$data['headline'],'school'=>$data['school_name'],'grade'=>$data['grade_level'],'subject'=>$data['subject'],'avatar'=>$data['avatar_stored_name']??null,'mime'=>$data['avatar_mime_type']??null,'show_avatar'=>(int)($data['show_avatar']??0),'id'=>$userId]);
    }

    public function updateSharing(int $userId, bool $enabled, bool $showName, bool $quick, bool $regenerate = false, ?string $customToken = null): string
    {
        $stmt=$this->db->prepare('SELECT share_token FROM portfolios WHERE user_id=:id'); $stmt->execute(['id'=>$userId]); $token=$stmt->fetchColumn();
        if ($enabled && $customToken!==null && $customToken!=='') $token=$customToken;
        elseif ($enabled && (!$token || $regenerate)) $token=bin2hex(random_bytes(32));
        // إيقاف المشاركة مؤقت، وليس حذف الرابط. يبقى الرابط نفسه عند إعادة التفعيل
        // أو عند تحديث الملف؛ لا يتغير إلا عبر زر «تجديد الرابط» صراحةً.
        $update=$this->db->prepare('UPDATE portfolios SET sharing_enabled=:enabled,show_public_name=:show,quick_share=:quick,share_token=:token,updated_at=CURRENT_TIMESTAMP WHERE user_id=:id');
        $update->execute(['enabled'=>(int)$enabled,'show'=>(int)$showName,'quick'=>(int)$quick,'token'=>$token,'id'=>$userId]);
        return (string)($token??'');
    }

    public function setPublicViewMode(int $userId, string $mode): void
    {
        $mode = $mode === 'standard' ? 'standard' : 'website';
        $stmt=$this->db->prepare('UPDATE portfolios SET public_view_mode=:mode,updated_at=CURRENT_TIMESTAMP WHERE user_id=:id');
        $stmt->execute(['mode'=>$mode,'id'=>$userId]);
    }

    public function setPublicPresentation(int $userId, string $presentation): void
    {
        $options=self::publicPresentationOptions();
        if (!array_key_exists($presentation,$options)) $presentation='landing';
        $stmt=$this->db->prepare('UPDATE portfolios SET public_presentation=:presentation,updated_at=CURRENT_TIMESTAMP WHERE user_id=:id');
        $stmt->execute(['presentation'=>$presentation,'id'=>$userId]);
    }

    public function setPublicLayoutMode(int $userId, string $mode): void
    {
        $mode = in_array($mode, ['sections','subjects'], true) ? $mode : 'single';
        // بعض قواعد البيانات القديمة تفرض CHECK يسمح بـ single/sections فقط.
        // نخزن نمط المواد في العمود الجديد، ونبقي القيمة القديمة متوافقة مع القيد.
        $subjects = $mode === 'subjects' ? 1 : 0;
        $storedMode = $subjects ? 'sections' : $mode;
        $stmt=$this->db->prepare('UPDATE portfolios SET public_layout_mode=:mode,public_subjects_mode=:subjects,updated_at=CURRENT_TIMESTAMP WHERE user_id=:id');
        $stmt->execute(['mode'=>$storedMode,'subjects'=>$subjects,'id'=>$userId]);
    }

    public function setSectionStructure(int $userId, string $structure): void
    {
        $structure = $structure === 'hierarchical' ? 'hierarchical' : 'flat';
        $stmt=$this->db->prepare('UPDATE portfolios SET section_structure=:structure,updated_at=CURRENT_TIMESTAMP WHERE user_id=:id');
        $stmt->execute(['structure'=>$structure,'id'=>$userId]);
    }

    public function applyStarterSections(int $userId, array $titles): void
    {
        $portfolio=$this->findForUser($userId); if (!$portfolio) return;
        $hasContent=trim((string)$portfolio['introduction'])!=='' || (bool)array_filter($portfolio['sections'],static fn(array $s):bool=>trim((string)$s['body'])!=='') || !empty($portfolio['evidence']);
        if ($hasContent) return;
        $sections=[];
        if (($portfolio['role']??'')==='teacher') {
            foreach (array_keys(self::STARTER_SECTIONS['teacher']) as $title) {
                $sections[]=['id'=>0,'client_key'=>'','parent_ref'=>'0','title'=>$title,'body'=>'','visibility'=>'private','enabled'=>1,'display_mode'=>'auto'];
            }
        } else {
            foreach ($titles as $title) $sections[]=['id'=>0,'client_key'=>'','parent_ref'=>'0','title'=>$title,'body'=>'أضف هنا تفاصيلك وبياناتك الخاصة بهذا القسم، ثم احذف هذا النص النموذجي.','visibility'=>'private','enabled'=>1,'display_mode'=>'auto'];
        }
        $this->saveForUser($userId,(string)$portfolio['title'],'اكتب نبذة تعريفية عن رحلتك وإنجازاتك، ثم عدّل هذا النص بما يناسبك.',$sections);
    }

    public function saveCustomTemplate(int $userId, string $name, array $config, bool $isPublic = false): int
    {
        $stmt=$this->db->prepare('INSERT INTO user_templates (user_id,name,config_json,is_public) VALUES (:user,:name,:config,:public)');
        $stmt->execute(['user'=>$userId,'name'=>$name,'config'=>json_encode($config,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR),'public'=>$isPublic?1:0]);
        return (int)$this->db->lastInsertId();
    }

    public function publicTemplates(): array
    {
        $stmt=$this->db->query("SELECT t.*,u.full_name FROM user_templates t JOIN users u ON u.id=t.user_id WHERE t.is_public=1 AND u.role='teacher' ORDER BY t.created_at DESC,t.id DESC");
        return $stmt->fetchAll();
    }

    public function customTemplates(int $userId): array
    {
        $stmt=$this->db->prepare('SELECT * FROM user_templates WHERE user_id=:user ORDER BY created_at DESC,id DESC');
        $stmt->execute(['user'=>$userId]); return $stmt->fetchAll();
    }

    public function setPublicSections(int $userId, array $sectionIds): void
    {
        $portfolio=$this->findForUser($userId);
        if (!$portfolio) return;
        $allowed=array_map('intval',array_column($portfolio['sections'],'id'));
        $selected=array_values(array_intersect($allowed,array_map('intval',$sectionIds)));
        $stmt=$this->db->prepare("UPDATE portfolio_sections SET visibility=CASE WHEN id IN (".($selected?implode(',',array_fill(0,count($selected),'?')):'NULL').") THEN 'public' ELSE 'private' END WHERE portfolio_id=?");
        $stmt->execute(array_merge($selected,[(int)$portfolio['id']]));
    }

    public function allForOwner(): array
    {
        $stmt=$this->db->query('SELECT p.*,u.full_name,u.email,u.role FROM portfolios p JOIN users u ON u.id=p.user_id ORDER BY p.updated_at DESC,p.id DESC');
        $items=$stmt->fetchAll();
        foreach ($items as &$item) $item['sections']=$this->sections((int)$item['id']);
        return $items;
    }

    public function incrementPublicViews(int $portfolioId): void
    {
        $stmt=$this->db->prepare('UPDATE portfolios SET public_view_count=public_view_count+1 WHERE id=:id');
        $stmt->execute(['id'=>$portfolioId]);
    }

    public function setTemplate(int $userId, string $templateId, array $custom): void
    {
        $stmt=$this->db->prepare('UPDATE portfolios SET template_id=:template,display_name=:display,background_color=:background,accent_color=:accent,font_family=:font,cover_style=:cover,appearance_mode=COALESCE(:appearance_mode,appearance_mode),updated_at=CURRENT_TIMESTAMP WHERE user_id=:id');
        $stmt->execute(['template'=>$templateId,'display'=>$custom['display_name'],'background'=>$custom['background_color'],'accent'=>$custom['accent_color'],'font'=>$custom['font_family'],'cover'=>$custom['cover_style'],'appearance_mode'=>isset($custom['appearance_mode'])&&in_array($custom['appearance_mode'],['light','dark'],true)?$custom['appearance_mode']:null,'id'=>$userId]);
    }

    public function completion(array $portfolio): array
    {
        $total = 2 + count($portfolio['sections']) + min(count($portfolio['evidence'] ?? []), 3);
        $completed = (trim($portfolio['title']) !== '' ? 1 : 0)
            + (trim($portfolio['introduction']) !== '' ? 1 : 0);
        foreach ($portfolio['sections'] as $section) {
            if (trim($section['body']) !== '') {
                $completed++;
            }
        }
        $completed += min(count($portfolio['evidence'] ?? []), 3);

        return [
            'completed' => $completed,
            'total' => $total,
            'percent' => $total > 0 ? (int) round(($completed / $total) * 100) : 0,
        ];
    }
}

<?php
declare(strict_types=1);

final class SubscriptionService
{
    private const PLAN_COLUMNS='p.id,p.slug,p.name,p.description,p.price_minor,p.currency,p.duration_days,p.max_evidence,p.max_sections,p.ai_enabled,p.custom_templates_enabled,p.public_sharing_enabled,p.file_upload_enabled,p.is_active';

    public static function forUser(PDO $db, int $userId): array
    {
        $sql='SELECT '.self::PLAN_COLUMNS.',s.ends_at AS subscription_ends_at FROM user_subscriptions s JOIN subscription_plans p ON p.id=s.plan_id WHERE s.user_id=:user AND s.status=\'active\' AND p.is_active=1 AND s.starts_at<=CURRENT_TIMESTAMP AND (s.ends_at IS NULL OR s.ends_at>CURRENT_TIMESTAMP) ORDER BY s.created_at DESC,s.id DESC LIMIT 1';
        $stmt=$db->prepare($sql); $stmt->execute(['user'=>$userId]); $plan=$stmt->fetch();
        if ($plan) return $plan;
        $trialStmt=$db->prepare("SELECT ends_at FROM user_trials WHERE user_id=:user AND status='active' AND ends_at>CURRENT_TIMESTAMP LIMIT 1"); $trialStmt->execute(['user'=>$userId]); $trialEnds=$trialStmt->fetchColumn();
        if ($trialEnds) {
            $free=$db->query('SELECT '.self::PLAN_COLUMNS.' FROM subscription_plans p WHERE p.slug=\'free\' AND p.is_active=1 LIMIT 1')->fetch();
            if ($free) { $free['slug']='free-trial'; $free['name']='التجربة المجانية'; $free['subscription_ends_at']=$trialEnds; return $free; }
        }
        $free=$db->prepare('SELECT '.self::PLAN_COLUMNS.',NULL AS subscription_ends_at FROM subscription_plans p WHERE p.slug=\'free\' AND p.is_active=1 LIMIT 1'); $free->execute();
        $plan=$free->fetch();
        if ($plan) return $plan;
        return ['id'=>0,'slug'=>'free','name'=>'الخطة الأساسية','price_minor'=>0,'currency'=>'SAR','max_evidence'=>24,'max_sections'=>24,'ai_enabled'=>1,'custom_templates_enabled'=>1,'public_sharing_enabled'=>1,'file_upload_enabled'=>1,'is_active'=>1,'subscription_ends_at'=>null,'duration_days'=>3650];
    }

    public static function allows(PDO $db, int $userId, string $feature): bool
    {
        $keys=['ai'=>'ai_enabled','custom_templates'=>'custom_templates_enabled','public_sharing'=>'public_sharing_enabled','file_upload'=>'file_upload_enabled'];
        $key=$keys[$feature]??null; if (!$key) return false;
        $plan=self::forUser($db,$userId); return (int)($plan[$key]??0)===1;
    }

    public static function limit(PDO $db, int $userId, string $feature): int
    {
        $keys=['max_evidence','max_sections']; if (!in_array($feature,$keys,true)) return 0;
        $plan=self::forUser($db,$userId); return max(0,(int)($plan[$feature]??0));
    }
}

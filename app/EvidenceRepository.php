<?php
declare(strict_types=1);

final class EvidenceRepository
{
    public function __construct(private readonly PDO $db) {}

    public function create(int $portfolioId, ?int $sectionId, array $file, array $data): int
    {
        $isLink=!empty($file['source_url']);
        $original=(string)($file['original_name']??'شاهد');
        $stmt = $this->db->prepare('INSERT INTO evidence (portfolio_id,section_id,title,description,evidence_type,original_name,stored_name,mime_type,file_size,evidence_date,tags,competency,visibility,source_url,source_kind) VALUES (:portfolio,:section,:title,:description,:evidence_type,:original,:stored,:mime,:size,:date,:tags,:competency,:visibility,:source_url,:source_kind)');
        $stmt->execute([
            'portfolio'=>$portfolioId,'section'=>$sectionId,'title'=>$data['title'] ?: pathinfo($original, PATHINFO_FILENAME),
            'description'=>$data['description'],'evidence_type'=>$data['evidence_type']??'','original'=>$original,'stored'=>$file['stored_name']??'',
            'mime'=>$file['mime_type']??($isLink?'text/uri-list':'application/octet-stream'),'size'=>(int)($file['file_size']??0),'date'=>$data['evidence_date'],'tags'=>$data['tags'],
            'competency'=>$data['competency'],'visibility'=>'private','source_url'=>$file['source_url']??'','source_kind'=>$file['source_kind']??($isLink?'url':'file'),
        ]);
        return (int)$this->db->lastInsertId();
    }

    public function owned(int $evidenceId, int $userId): ?array
    {
        $stmt = $this->db->prepare('SELECT e.*,p.user_id FROM evidence e JOIN portfolios p ON p.id=e.portfolio_id WHERE e.id=:evidence AND p.user_id=:user LIMIT 1');
        $stmt->execute(['evidence'=>$evidenceId,'user'=>$userId]);
        return $stmt->fetch() ?: null;
    }

    public function publicForShare(int $evidenceId, string $token): ?array
    {
        $stmt = $this->db->prepare("SELECT e.*,p.user_id FROM evidence e JOIN portfolios p ON p.id=e.portfolio_id JOIN portfolio_sections s ON s.id=e.section_id WHERE e.id=:id AND p.share_token=:token AND p.sharing_enabled=1 AND e.visibility='public' AND s.visibility='public' AND s.enabled=1 LIMIT 1");
        $stmt->execute(['id'=>$evidenceId,'token'=>$token]);
        return $stmt->fetch() ?: null;
    }

    public function delete(int $evidenceId, int $userId): ?string
    {
        $item = $this->owned($evidenceId,$userId);
        if (!$item) return null;
        $stmt = $this->db->prepare('DELETE FROM evidence WHERE id=:id');
        $stmt->execute(['id'=>$evidenceId]);
        return (string)$item['stored_name'];
    }

    public function setVisibility(int $evidenceId, int $userId, string $visibility): bool
    {
        $stmt = $this->db->prepare('UPDATE evidence SET visibility=:visibility WHERE id=:id AND portfolio_id=(SELECT id FROM portfolios WHERE user_id=:user)');
        $stmt->execute(['visibility'=>$visibility,'id'=>$evidenceId,'user'=>$userId]);
        return $stmt->rowCount() === 1;
    }

    public function reorder(int $userId, array $ids): void
    {
        $portfolio=$this->db->prepare('SELECT id FROM portfolios WHERE user_id=:user'); $portfolio->execute(['user'=>$userId]); $portfolioId=(int)$portfolio->fetchColumn();
        if (!$portfolioId) return;
        $allowed=$this->db->prepare('SELECT id FROM evidence WHERE portfolio_id=:portfolio'); $allowed->execute(['portfolio'=>$portfolioId]); $valid=array_map('intval',array_column($allowed->fetchAll(),'id'));
        $ids=array_values(array_intersect($valid,array_map('intval',$ids))); $update=$this->db->prepare('UPDATE evidence SET sort_order=:sort WHERE id=:id AND portfolio_id=:portfolio');
        foreach ($ids as $index=>$id) $update->execute(['sort'=>$index,'id'=>$id,'portfolio'=>$portfolioId]);
    }
}

INSERT INTO portfolio_sections (portfolio_id,title,parent_id,sort_order)
SELECT p.id,'التفكير الناقد وحل المشكلات',s.id,
       COALESCE((SELECT MAX(child.sort_order)+1 FROM portfolio_sections child WHERE child.portfolio_id=p.id),0)
FROM portfolios p
JOIN portfolio_sections s ON s.portfolio_id=p.id AND s.title='مهاراتي وإنجازاتي' AND s.parent_id IS NULL
JOIN users u ON u.id=p.user_id AND u.role='student'
WHERE NOT EXISTS (
    SELECT 1 FROM portfolio_sections existing
    WHERE existing.portfolio_id=p.id AND existing.parent_id=s.id AND existing.title='التفكير الناقد وحل المشكلات'
);

-- Every signup that never issued an invoice, with the exact stage they stopped at
-- and the mobile the team can call. Newest first.
SELECT
  u.id, u.name, u.phone, u.email,
  DATE(u.created_at)                                                AS signed_up,
  DATEDIFF(NOW(), u.created_at)                                     AS days_ago,
  CASE
    WHEN c.id IS NULL                                   THEN '1. no company row'
    WHEN c.name IS NULL OR c.name = ''
      OR c.state_id IS NULL                             THEN '2. abandoned business form'
    WHEN c.onboarded_at IS NULL                         THEN '3. abandoned setup wizard'
    WHEN NOT EXISTS (SELECT 1 FROM customers cu WHERE cu.user_id = u.id)
                                                        THEN '4. no customer added'
    WHEN NOT EXISTS (SELECT 1 FROM invoices i WHERE i.user_id = u.id)
                                                        THEN '5. never opened invoice form'
    ELSE                                                     '6. draft only, never finalised'
  END                                                               AS stopped_at,
  (SELECT COUNT(*) FROM invoices i WHERE i.user_id = u.id)          AS drafts,
  c.gstin IS NOT NULL AND c.gstin <> ''                             AS has_gstin
FROM users u
LEFT JOIN companies c ON c.id = (SELECT MIN(c2.id) FROM companies c2 WHERE c2.user_id = u.id)
WHERE u.erased_at IS NULL
  AND u.is_super_admin = 0
  AND NOT EXISTS (SELECT 1 FROM invoices i WHERE i.user_id = u.id AND i.finalized_at IS NOT NULL)
ORDER BY u.created_at DESC;

-- Activation funnel: where new signups stop.
-- Run on production (read-only). Every stage is cumulative and counts distinct users.
SELECT
  COUNT(*)                                                                    AS s1_registered,
  SUM(has_company)                                                            AS s2_company_row,
  SUM(business_complete)                                                      AS s3_business_complete,
  SUM(onboarded)                                                              AS s4_finished_setup,
  SUM(has_customer)                                                           AS s5_added_customer,
  SUM(has_invoice)                                                            AS s6_started_invoice,
  SUM(has_final)                                                              AS s7_issued_invoice,
  SUM(has_2plus_final)                                                        AS s8_repeat_user,
  ROUND(100.0 * SUM(has_final) / NULLIF(COUNT(*), 0), 1)                      AS pct_activated
FROM (
  SELECT
    u.id,
    MAX(c.id IS NOT NULL)                                                     AS has_company,
    MAX(c.name IS NOT NULL AND c.name <> '' AND c.state_id IS NOT NULL
        AND (c.gstin IS NULL OR c.gstin = '' OR
             (c.address_line1 IS NOT NULL AND c.address_line1 <> '')))        AS business_complete,
    MAX(c.onboarded_at IS NOT NULL)                                           AS onboarded,
    (SELECT COUNT(*) > 0 FROM customers cu WHERE cu.user_id = u.id)           AS has_customer,
    (SELECT COUNT(*) > 0 FROM invoices i WHERE i.user_id = u.id)              AS has_invoice,
    (SELECT COUNT(*) > 0 FROM invoices i
      WHERE i.user_id = u.id AND i.finalized_at IS NOT NULL)                  AS has_final,
    (SELECT COUNT(*) > 1 FROM invoices i
      WHERE i.user_id = u.id AND i.finalized_at IS NOT NULL)                  AS has_2plus_final
  FROM users u
  LEFT JOIN companies c ON c.user_id = u.id
  WHERE u.erased_at IS NULL AND u.is_super_admin = 0
  GROUP BY u.id
) f;

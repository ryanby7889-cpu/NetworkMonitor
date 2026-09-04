-- NetworkMonitor migration 003
-- Separate router selection (is_active) from real connection status (status).
-- Safe to run on an existing network_monitor database.

ALTER TABLE router
    ADD COLUMN is_active TINYINT(1) NOT NULL DEFAULT 0 AFTER status;

-- Preserve the router currently marked ONLINE as the active router.
UPDATE router SET is_active = 0;
UPDATE router SET is_active = 1 WHERE id = (
    SELECT id FROM (
        SELECT id FROM router WHERE status = 'ONLINE' ORDER BY id ASC LIMIT 1
    ) AS selected_router
);

-- If no router was ONLINE, use the first router as active.
UPDATE router SET is_active = 1
WHERE id = (SELECT id FROM (SELECT id FROM router ORDER BY id ASC LIMIT 1) AS first_router)
  AND NOT EXISTS (SELECT 1 FROM (SELECT id FROM router WHERE is_active = 1) AS active_router);

CREATE INDEX idx_router_active ON router(is_active);

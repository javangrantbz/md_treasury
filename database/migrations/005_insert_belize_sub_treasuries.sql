-- =============================================================
-- Migration: 005_insert_belize_sub_treasuries
-- Description: Seed official Belize sub-treasury locations
-- =============================================================

INSERT INTO sub_treasuries (
    uuid,
    department_id,
    sub_treasury_code,
    sub_treasury_name,
    district,
    is_active
)
SELECT UUID(), 28, 'ST-OWK', 'Sub Treasury Orange Walk', 'Orange Walk', 1
WHERE NOT EXISTS (
    SELECT 1 FROM sub_treasuries WHERE sub_treasury_name = 'Sub Treasury Orange Walk'
);

INSERT INTO sub_treasuries (
    uuid,
    department_id,
    sub_treasury_code,
    sub_treasury_name,
    district,
    is_active
)
SELECT UUID(), 28, 'ST-BMP', 'Sub Treasury Belmopan', 'Cayo', 1
WHERE NOT EXISTS (
    SELECT 1 FROM sub_treasuries WHERE sub_treasury_name = 'Sub Treasury Belmopan'
);

INSERT INTO sub_treasuries (
    uuid,
    department_id,
    sub_treasury_code,
    sub_treasury_name,
    district,
    is_active
)
SELECT UUID(), 28, 'ST-SIG', 'Sub Treasury San Ignacio', 'Cayo', 1
WHERE NOT EXISTS (
    SELECT 1 FROM sub_treasuries WHERE sub_treasury_name = 'Sub Treasury San Ignacio'
);

INSERT INTO sub_treasuries (
    uuid,
    department_id,
    sub_treasury_code,
    sub_treasury_name,
    district,
    is_active
)
SELECT UUID(), 28, 'ST-DGR', 'Sub Treasury Dangriga', 'Stann Creek', 1
WHERE NOT EXISTS (
    SELECT 1 FROM sub_treasuries WHERE sub_treasury_name = 'Sub Treasury Dangriga'
);

INSERT INTO sub_treasuries (
    uuid,
    department_id,
    sub_treasury_code,
    sub_treasury_name,
    district,
    is_active
)
SELECT UUID(), 28, 'ST-PGD', 'Sub Treasury Punta Gorda', 'Toledo', 1
WHERE NOT EXISTS (
    SELECT 1 FROM sub_treasuries WHERE sub_treasury_name = 'Sub Treasury Punta Gorda'
);

INSERT INTO sub_treasuries (
    uuid,
    department_id,
    sub_treasury_code,
    sub_treasury_name,
    district,
    is_active
)
SELECT UUID(), 28, 'ST-SPD', 'Sub Treasury San Pedro', 'Belize', 1
WHERE NOT EXISTS (
    SELECT 1 FROM sub_treasuries WHERE sub_treasury_name = 'Sub Treasury San Pedro'
);

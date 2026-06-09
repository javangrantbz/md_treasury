-- Live-safe verified Belize sub-treasury sync
-- Purpose:
-- 1. Add deleted_at only where this sync needs it
-- 2. Map all verified sub-treasuries to Treasury Department
-- 3. Upsert the 7 official Belize sub-treasury rows

ALTER TABLE departments ADD COLUMN IF NOT EXISTS deleted_at DATETIME NULL;
ALTER TABLE sub_treasuries ADD COLUMN IF NOT EXISTS deleted_at DATETIME NULL;

SET @treasury_department_id := (
    SELECT id
    FROM departments
    WHERE deleted_at IS NULL
      AND name = 'Treasury Department'
    ORDER BY id
    LIMIT 1
);

UPDATE sub_treasuries
SET department_id = @treasury_department_id,
    sub_treasury_code = '18041',
    sub_treasury_name = 'Sub Treasury San Pedro',
    district = 'Belize',
    is_active = 1,
    deleted_at = NULL
WHERE @treasury_department_id IS NOT NULL
  AND (sub_treasury_code = '18041' OR sub_treasury_name = 'Sub Treasury San Pedro');

INSERT INTO sub_treasuries (
    uuid,
    department_id,
    sub_treasury_code,
    sub_treasury_name,
    district,
    is_active
)
SELECT UUID(), @treasury_department_id, '18041', 'Sub Treasury San Pedro', 'Belize', 1
WHERE @treasury_department_id IS NOT NULL
  AND NOT EXISTS (
      SELECT 1
      FROM sub_treasuries
      WHERE sub_treasury_code = '18041'
         OR sub_treasury_name = 'Sub Treasury San Pedro'
  );

UPDATE sub_treasuries
SET department_id = @treasury_department_id,
    sub_treasury_code = '18152',
    sub_treasury_name = 'Sub Treasury Corozal',
    district = 'Corozal',
    is_active = 1,
    deleted_at = NULL
WHERE @treasury_department_id IS NOT NULL
  AND (sub_treasury_code = '18152' OR sub_treasury_name = 'Sub Treasury Corozal');

INSERT INTO sub_treasuries (
    uuid,
    department_id,
    sub_treasury_code,
    sub_treasury_name,
    district,
    is_active
)
SELECT UUID(), @treasury_department_id, '18152', 'Sub Treasury Corozal', 'Corozal', 1
WHERE @treasury_department_id IS NOT NULL
  AND NOT EXISTS (
      SELECT 1
      FROM sub_treasuries
      WHERE sub_treasury_code = '18152'
         OR sub_treasury_name = 'Sub Treasury Corozal'
  );

UPDATE sub_treasuries
SET department_id = @treasury_department_id,
    sub_treasury_code = '18163',
    sub_treasury_name = 'Sub Treasury Orange Walk',
    district = 'Orange Walk',
    is_active = 1,
    deleted_at = NULL
WHERE @treasury_department_id IS NOT NULL
  AND (sub_treasury_code = '18163' OR sub_treasury_name = 'Sub Treasury Orange Walk');

INSERT INTO sub_treasuries (
    uuid,
    department_id,
    sub_treasury_code,
    sub_treasury_name,
    district,
    is_active
)
SELECT UUID(), @treasury_department_id, '18163', 'Sub Treasury Orange Walk', 'Orange Walk', 1
WHERE @treasury_department_id IS NOT NULL
  AND NOT EXISTS (
      SELECT 1
      FROM sub_treasuries
      WHERE sub_treasury_code = '18163'
         OR sub_treasury_name = 'Sub Treasury Orange Walk'
  );

UPDATE sub_treasuries
SET department_id = @treasury_department_id,
    sub_treasury_code = '18178',
    sub_treasury_name = 'Sub Treasury Belmopan',
    district = 'Cayo',
    is_active = 1,
    deleted_at = NULL
WHERE @treasury_department_id IS NOT NULL
  AND (sub_treasury_code = '18178' OR sub_treasury_name = 'Sub Treasury Belmopan');

INSERT INTO sub_treasuries (
    uuid,
    department_id,
    sub_treasury_code,
    sub_treasury_name,
    district,
    is_active
)
SELECT UUID(), @treasury_department_id, '18178', 'Sub Treasury Belmopan', 'Cayo', 1
WHERE @treasury_department_id IS NOT NULL
  AND NOT EXISTS (
      SELECT 1
      FROM sub_treasuries
      WHERE sub_treasury_code = '18178'
         OR sub_treasury_name = 'Sub Treasury Belmopan'
  );

UPDATE sub_treasuries
SET department_id = @treasury_department_id,
    sub_treasury_code = '18184',
    sub_treasury_name = 'Sub Treasury San Ignacio',
    district = 'Cayo',
    is_active = 1,
    deleted_at = NULL
WHERE @treasury_department_id IS NOT NULL
  AND (sub_treasury_code = '18184' OR sub_treasury_name = 'Sub Treasury San Ignacio');

INSERT INTO sub_treasuries (
    uuid,
    department_id,
    sub_treasury_code,
    sub_treasury_name,
    district,
    is_active
)
SELECT UUID(), @treasury_department_id, '18184', 'Sub Treasury San Ignacio', 'Cayo', 1
WHERE @treasury_department_id IS NOT NULL
  AND NOT EXISTS (
      SELECT 1
      FROM sub_treasuries
      WHERE sub_treasury_code = '18184'
         OR sub_treasury_name = 'Sub Treasury San Ignacio'
  );

UPDATE sub_treasuries
SET department_id = @treasury_department_id,
    sub_treasury_code = '18195',
    sub_treasury_name = 'Sub Treasury Dangriga',
    district = 'Stann Creek',
    is_active = 1,
    deleted_at = NULL
WHERE @treasury_department_id IS NOT NULL
  AND (sub_treasury_code = '18195' OR sub_treasury_name = 'Sub Treasury Dangriga');

INSERT INTO sub_treasuries (
    uuid,
    department_id,
    sub_treasury_code,
    sub_treasury_name,
    district,
    is_active
)
SELECT UUID(), @treasury_department_id, '18195', 'Sub Treasury Dangriga', 'Stann Creek', 1
WHERE @treasury_department_id IS NOT NULL
  AND NOT EXISTS (
      SELECT 1
      FROM sub_treasuries
      WHERE sub_treasury_code = '18195'
         OR sub_treasury_name = 'Sub Treasury Dangriga'
  );

UPDATE sub_treasuries
SET department_id = @treasury_department_id,
    sub_treasury_code = '18206',
    sub_treasury_name = 'Sub Treasury Punta Gorda',
    district = 'Toledo',
    is_active = 1,
    deleted_at = NULL
WHERE @treasury_department_id IS NOT NULL
  AND (sub_treasury_code = '18206' OR sub_treasury_name = 'Sub Treasury Punta Gorda');

INSERT INTO sub_treasuries (
    uuid,
    department_id,
    sub_treasury_code,
    sub_treasury_name,
    district,
    is_active
)
SELECT UUID(), @treasury_department_id, '18206', 'Sub Treasury Punta Gorda', 'Toledo', 1
WHERE @treasury_department_id IS NOT NULL
  AND NOT EXISTS (
      SELECT 1
      FROM sub_treasuries
      WHERE sub_treasury_code = '18206'
         OR sub_treasury_name = 'Sub Treasury Punta Gorda'
  );

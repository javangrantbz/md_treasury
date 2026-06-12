-- =============================================================
-- Migration: 006_align_sub_treasury_codes_with_belize_estimates
-- Description: Remove Belize City sub-treasury and align codes to
--              published Belize Ministry of Finance cost centres
-- =============================================================

UPDATE registers
SET sub_treasury_id = NULL
WHERE sub_treasury_id IN (
    SELECT id FROM (
        SELECT id
        FROM sub_treasuries
        WHERE sub_treasury_name = 'Sub Treasury Belize City'
    ) AS treasury_belize_city
);

DELETE FROM sub_treasuries
WHERE sub_treasury_name = 'Sub Treasury Belize City';

UPDATE sub_treasuries
SET sub_treasury_code = '18152',
    district = 'Corozal'
WHERE sub_treasury_name = 'Sub Treasury Corozal';

UPDATE sub_treasuries
SET sub_treasury_code = '18163',
    district = 'Orange Walk'
WHERE sub_treasury_name = 'Sub Treasury Orange Walk';

UPDATE sub_treasuries
SET sub_treasury_code = '18178',
    district = 'Cayo'
WHERE sub_treasury_name = 'Sub Treasury Belmopan';

UPDATE sub_treasuries
SET sub_treasury_code = '18184',
    district = 'Cayo'
WHERE sub_treasury_name = 'Sub Treasury San Ignacio';

UPDATE sub_treasuries
SET sub_treasury_code = '18195',
    district = 'Stann Creek'
WHERE sub_treasury_name = 'Sub Treasury Dangriga';

UPDATE sub_treasuries
SET sub_treasury_code = '18206',
    district = 'Toledo'
WHERE sub_treasury_name = 'Sub Treasury Punta Gorda';

UPDATE sub_treasuries
SET sub_treasury_code = '18041',
    district = 'Belize'
WHERE sub_treasury_name = 'Sub Treasury San Pedro';

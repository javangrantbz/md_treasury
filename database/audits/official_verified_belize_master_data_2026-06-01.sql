-- Official Belize master-data reference seed
-- Generated from Belize Ministry of Finance public estimates checked on 2026-06-01.
-- Purpose:
--   1. keep a clean, reviewable set of online-verified cost centres
--   2. keep the cleaned sub-treasury cost-centre list
--   3. keep official revenue/item codes relevant to the current cost_center_activities table
--
-- Notes:
-- - This file creates separate reference tables. It does not overwrite your live master-data tables.
-- - `current_app_name` is included where a matching row exists in the current app database.
-- - `current_app_activity_example` shows the closest current `cost_center_activities.activity_name` pattern.
-- - The app's `CCA-*` activity codes are internal and are not treated as official government codes here.

DROP TABLE IF EXISTS official_verified_cost_centers;
CREATE TABLE official_verified_cost_centers (
    cost_center_code VARCHAR(10) NOT NULL PRIMARY KEY,
    official_name VARCHAR(191) NOT NULL,
    current_exists_in_app TINYINT(1) NOT NULL DEFAULT 0,
    current_app_name VARCHAR(191) DEFAULT NULL,
    source_url VARCHAR(500) NOT NULL,
    source_note VARCHAR(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO official_verified_cost_centers
    (cost_center_code, official_name, current_exists_in_app, current_app_name, source_url, source_note)
VALUES
    ('12017', 'General Registry', 1, 'General Registry', 'https://www.mof.gov.bz/wp-content/uploads/2023/09/z5ayi3z3.pdf', 'Judiciary summary / historical estimates'),
    ('12021', 'Court of Appeal', 0, NULL, 'https://www.mof.gov.bz/wp-content/uploads/2023/09/z5ayi3z3.pdf', 'Found online, not present in current app cost_centers'),
    ('12031', 'Supreme Court', 1, 'Supreme Court', 'https://www.mof.gov.bz/wp-content/uploads/2023/09/z5ayi3z3.pdf', 'Judiciary summary / historical estimates'),
    ('12041', 'Magistrate Court Belize City', 1, 'Magistrate Court Belize City', 'https://www.mof.gov.bz/wp-content/uploads/2023/09/z5ayi3z3.pdf', 'Judiciary summary / historical estimates'),
    ('12052', 'Magistrate Court Corozal', 1, 'Magistrate Court Corozal', 'https://www.mof.gov.bz/wp-content/uploads/2023/09/z5ayi3z3.pdf', 'Judiciary summary / historical estimates'),
    ('12063', 'Magistrate Court Orange Walk', 1, 'Magistrate Court Orange Walk', 'https://www.mof.gov.bz/wp-content/uploads/2023/09/z5ayi3z3.pdf', 'Judiciary summary / historical estimates'),
    ('12078', 'Magistrate Court Belmopan', 1, 'Magistrate Court Belmopan', 'https://www.mof.gov.bz/wp-content/uploads/2023/09/z5ayi3z3.pdf', 'Judiciary summary / historical estimates'),
    ('12084', 'Magistrate Court San Ignacio', 1, 'Magistrate Court San Ignacio', 'https://www.mof.gov.bz/wp-content/uploads/2023/09/z5ayi3z3.pdf', 'Judiciary summary / historical estimates'),
    ('12095', 'Magistrate Court Dangriga', 1, 'Magistrate Court Dangriga', 'https://www.mof.gov.bz/wp-content/uploads/2023/09/z5ayi3z3.pdf', 'Judiciary summary / historical estimates'),
    ('12106', 'Magistrate Court Punta Gorda', 1, 'Magistrate Court Punta Gorda', 'https://www.mof.gov.bz/wp-content/uploads/2023/09/z5ayi3z3.pdf', 'Judiciary summary / historical estimates'),
    ('12111', 'Magistrate Court San Pedro', 1, 'Magistrate Court San Pedro', 'https://www.mof.gov.bz/wp-content/uploads/2023/09/z5ayi3z3.pdf', 'Judiciary summary / historical estimates'),
    ('12125', 'Magistrate Court Independence', 1, 'Magistrate Court Indepedence', 'https://www.mof.gov.bz/wp-content/uploads/2023/09/z5ayi3z3.pdf', 'Official spelling normalized; current app name has a typo'),
    ('12128', 'BELIPO', 1, 'Belize Intellectual Property Office (BELIPO)', 'https://www.mof.gov.bz/wp-content/uploads/2023/09/z5ayi3z3.pdf', 'Judiciary summary / historical estimates'),
    ('12138', 'Belize Company Registry', 0, NULL, 'https://www.mof.gov.bz/wp-content/uploads/2023/09/z5ayi3z3.pdf', 'Found online, not present in current app cost_centers'),
    ('18017', 'General Administration', 1, 'Ministry of Finance - General Administration', 'https://www.mof.gov.bz/wp-content/uploads/2023/09/z5ayi3z3.pdf', 'MOF summary uses shorter label'),
    ('18019', 'Ministry of Finance - Internal Audit Unit', 0, NULL, 'https://mof.gov.bz/wp-content/uploads/2025/05/Draft-Estimates-of-Revenue-and-Expenditure%E2%80%93Fiscal-Year-2025-2026.pdf', 'Appendix D; found online, not present in current app cost_centers'),
    ('18058', 'Public Debt Services', 1, 'Ministry of Finance - Public Debt Services Management', 'https://www.mof.gov.bz/wp-content/uploads/2023/09/z5ayi3z3.pdf', 'MOF summary uses shorter label'),
    ('18068', 'Central Information Technology', 1, 'Central Information Technology Office', 'https://www.mof.gov.bz/wp-content/uploads/2023/09/z5ayi3z3.pdf', 'MOF summary uses shorter label'),
    ('18071', 'Treasury - Belize City', 1, 'Treasury Department (Head Office)', 'https://mof.gov.bz/wp-content/uploads/2023/09/yfqtsrbe.pdf', 'Official Treasury cost-centre label differs from current app label'),
    ('18088', 'IMMARBE/HSFU', 0, NULL, 'https://mof.gov.bz/wp-content/uploads/2025/05/Draft-Estimates-of-Revenue-and-Expenditure%E2%80%93Fiscal-Year-2025-2026.pdf', 'Appendix D; found online, not present in current app cost_centers'),
    ('18098', 'Ministry of Finance - Procurement Unit', 0, NULL, 'https://mof.gov.bz/wp-content/uploads/2025/05/Draft-Estimates-of-Revenue-and-Expenditure%E2%80%93Fiscal-Year-2025-2026.pdf', 'Appendix D; found online, not present in current app cost_centers'),
    ('18211', 'Customs & Excise Belize City', 1, 'Customs & Excise Belize City', 'https://www.mof.gov.bz/wp-content/uploads/2023/09/z5ayi3z3.pdf', 'Customs cost-centre page / summary'),
    ('18511', 'Belize Tax Service - Headquarters', 1, 'Belize Tax Service -Headquarters', 'https://mof.gov.bz/wp-content/uploads/2025/05/Draft-Estimates-of-Revenue-and-Expenditure%E2%80%93Fiscal-Year-2025-2026.pdf', 'Appendix D'),
    ('18521', 'Belize Tax Service - San Pedro', 1, 'Belize Tax Service - San Pedro', 'https://mof.gov.bz/wp-content/uploads/2025/05/Draft-Estimates-of-Revenue-and-Expenditure%E2%80%93Fiscal-Year-2025-2026.pdf', 'Appendix D'),
    ('18522', 'Belize Tax Service - Corozal', 1, 'Belize Tax Service - Corozal', 'https://mof.gov.bz/wp-content/uploads/2025/05/Draft-Estimates-of-Revenue-and-Expenditure%E2%80%93Fiscal-Year-2025-2026.pdf', 'Appendix D'),
    ('18523', 'Belize Tax Service - Orange Walk', 1, 'Belize Tax Service - Orange Walk', 'https://mof.gov.bz/wp-content/uploads/2025/05/Draft-Estimates-of-Revenue-and-Expenditure%E2%80%93Fiscal-Year-2025-2026.pdf', 'Appendix D'),
    ('18524', 'Belize Tax Service - San Ignacio', 1, 'Belize Tax Service - San Ignacio', 'https://mof.gov.bz/wp-content/uploads/2025/05/Draft-Estimates-of-Revenue-and-Expenditure%E2%80%93Fiscal-Year-2025-2026.pdf', 'Appendix D'),
    ('18525', 'Belize Tax Service - Dangriga', 1, 'Belize Tax Service - Dangriga', 'https://mof.gov.bz/wp-content/uploads/2025/05/Draft-Estimates-of-Revenue-and-Expenditure%E2%80%93Fiscal-Year-2025-2026.pdf', 'Appendix D'),
    ('18526', 'Belize Tax Service - Punta Gorda', 1, 'Belize Tax Service - Punta Gorda', 'https://mof.gov.bz/wp-content/uploads/2025/05/Draft-Estimates-of-Revenue-and-Expenditure%E2%80%93Fiscal-Year-2025-2026.pdf', 'Appendix D'),
    ('18528', 'Belize Tax Service - Belmopan', 1, 'Belize Tax Service - Belmopan', 'https://mof.gov.bz/wp-content/uploads/2025/05/Draft-Estimates-of-Revenue-and-Expenditure%E2%80%93Fiscal-Year-2025-2026.pdf', 'Appendix D'),
    ('19031', 'Central Health Region', 1, 'Central Health Region', 'https://mof.gov.bz/wp-content/uploads/2025/05/Draft-Estimates-of-Revenue-and-Expenditure%E2%80%93Fiscal-Year-2025-2026.pdf', 'Appendix D'),
    ('19068', 'Drug Inspectorate Unit', 1, 'Drug Inspectorate Unit', 'https://mof.gov.bz/wp-content/uploads/2025/05/Draft-Estimates-of-Revenue-and-Expenditure%E2%80%93Fiscal-Year-2025-2026.pdf', 'Appendix D'),
    ('19074', 'San Ignacio Community Hospital', 1, 'San Ignacio Community Hospital', 'https://mof.gov.bz/wp-content/uploads/2025/05/Draft-Estimates-of-Revenue-and-Expenditure%E2%80%93Fiscal-Year-2025-2026.pdf', 'Appendix D'),
    ('19083', 'Northern Regional Hospital', 1, 'Northern Regional Hospital', 'https://mof.gov.bz/wp-content/uploads/2025/05/Draft-Estimates-of-Revenue-and-Expenditure%E2%80%93Fiscal-Year-2025-2026.pdf', 'Appendix D'),
    ('19092', 'Corozal Community Hospital', 1, 'Corozal Community Hospital', 'https://mof.gov.bz/wp-content/uploads/2025/05/Draft-Estimates-of-Revenue-and-Expenditure%E2%80%93Fiscal-Year-2025-2026.pdf', 'Appendix D'),
    ('19105', 'Southern Regional Hospital', 1, 'Southern Regional Hospital', 'https://mof.gov.bz/wp-content/uploads/2025/05/Draft-Estimates-of-Revenue-and-Expenditure%E2%80%93Fiscal-Year-2025-2026.pdf', 'Appendix D'),
    ('19116', 'Toledo Community Hospital', 1, 'Toledo Community Hospital', 'https://mof.gov.bz/wp-content/uploads/2025/05/Draft-Estimates-of-Revenue-and-Expenditure%E2%80%93Fiscal-Year-2025-2026.pdf', 'Appendix D'),
    ('19131', 'Central Medical Laboratory Services', 1, 'Central Medical Laboratory Services', 'https://mof.gov.bz/wp-content/uploads/2025/05/Draft-Estimates-of-Revenue-and-Expenditure%E2%80%93Fiscal-Year-2025-2026.pdf', 'Appendix D'),
    ('19168', 'Western Regional Hospital', 1, 'Western Regional Hospital', 'https://mof.gov.bz/wp-content/uploads/2025/05/Draft-Estimates-of-Revenue-and-Expenditure%E2%80%93Fiscal-Year-2025-2026.pdf', 'Appendix D'),
    ('19208', 'Licensing and Accreditation Unit', 1, 'Licensing and Accreditation Unit', 'https://mof.gov.bz/wp-content/uploads/2025/05/Draft-Estimates-of-Revenue-and-Expenditure%E2%80%93Fiscal-Year-2025-2026.pdf', 'Appendix D'),
    ('19288', 'Pharmacy Unit', 1, 'Pharmacy Unit', 'https://mof.gov.bz/wp-content/uploads/2025/05/Draft-Estimates-of-Revenue-and-Expenditure%E2%80%93Fiscal-Year-2025-2026.pdf', 'Appendix D'),
    ('19291', 'Dr. Otto Rodriguez Polyclinic', 1, 'Dr. Otto Rodriguez Polyclinic', 'https://mof.gov.bz/wp-content/uploads/2025/05/Draft-Estimates-of-Revenue-and-Expenditure%E2%80%93Fiscal-Year-2025-2026.pdf', 'Appendix D'),
    ('19298', 'Project Management Unit (PMU)', 1, 'Project Management Unit (PMU)', 'https://mof.gov.bz/wp-content/uploads/2025/05/Draft-Estimates-of-Revenue-and-Expenditure%E2%80%93Fiscal-Year-2025-2026.pdf', 'Appendix D'),
    ('24017', 'Ministry of Foreign Trade - General Administration', 1, 'Ministry of Foreign Trade - General Administration', 'https://mof.gov.bz/wp-content/uploads/2025/05/Draft-Estimates-of-Revenue-and-Expenditure%E2%80%93Fiscal-Year-2025-2026.pdf', 'Appendix D'),
    ('25021', 'Belize Broadcasting Authority', 1, 'Belize Broadcasting Authority', 'https://www.mof.gov.bz/wp-content/uploads/2023/09/z5ayi3z3.pdf', 'Summary and historical estimates'),
    ('25028', 'National Institute of Culture and History', 1, 'National Institute of Culture and History', 'https://mof.gov.bz/wp-content/uploads/2025/05/Draft-Estimates-of-Revenue-and-Expenditure%E2%80%93Fiscal-Year-2025-2026.pdf', 'Appendix D'),
    ('30258', 'Immigration Services - Head Office', 1, 'Immigration Services - Head Office', 'https://mof.gov.bz/wp-content/uploads/2025/05/Draft-Estimates-of-Revenue-and-Expenditure%E2%80%93Fiscal-Year-2025-2026.pdf', 'Appendix D'),
    ('30261', 'Immigration Services - Belize City', 1, 'Immigration Services - Belize City', 'https://mof.gov.bz/wp-content/uploads/2025/05/Draft-Estimates-of-Revenue-and-Expenditure%E2%80%93Fiscal-Year-2025-2026.pdf', 'Appendix D'),
    ('30271', 'Immigration Services - Passport Office', 1, 'Immigration Services - Passport Office', 'https://mof.gov.bz/wp-content/uploads/2025/05/Draft-Estimates-of-Revenue-and-Expenditure%E2%80%93Fiscal-Year-2025-2026.pdf', 'Appendix D'),
    ('30288', 'Immigration Services - Refugee Department', 1, 'Immigration Services - Refugee Department', 'https://mof.gov.bz/wp-content/uploads/2025/05/Draft-Estimates-of-Revenue-and-Expenditure%E2%80%93Fiscal-Year-2025-2026.pdf', 'Appendix D'),
    ('30402', 'Immigration Services - Corozal', 1, 'Immigration Services - Corozal', 'https://mof.gov.bz/wp-content/uploads/2025/05/Draft-Estimates-of-Revenue-and-Expenditure%E2%80%93Fiscal-Year-2025-2026.pdf', 'Appendix D'),
    ('30413', 'Immigration Services - Orange Walk', 1, 'Immigration Services - Orange Walk', 'https://mof.gov.bz/wp-content/uploads/2025/05/Draft-Estimates-of-Revenue-and-Expenditure%E2%80%93Fiscal-Year-2025-2026.pdf', 'Appendix D'),
    ('30424', 'Immigration Services - Cayo', 1, 'Immigration Services - Cayo', 'https://mof.gov.bz/wp-content/uploads/2025/05/Draft-Estimates-of-Revenue-and-Expenditure%E2%80%93Fiscal-Year-2025-2026.pdf', 'Appendix D'),
    ('30435', 'Immigration Services - Stann Creek', 1, 'Immigration Services - Stann Creek', 'https://mof.gov.bz/wp-content/uploads/2025/05/Draft-Estimates-of-Revenue-and-Expenditure%E2%80%93Fiscal-Year-2025-2026.pdf', 'Appendix D'),
    ('30446', 'Immigration Services - Toledo', 1, 'Immigration Services - Toledo', 'https://mof.gov.bz/wp-content/uploads/2025/05/Draft-Estimates-of-Revenue-and-Expenditure%E2%80%93Fiscal-Year-2025-2026.pdf', 'Appendix D'),
    ('33162', 'District Post Office - Corozal', 1, 'District Post Office - Corozal', 'https://www.mof.gov.bz/wp-content/uploads/2023/09/5uzn34qr.pdf', 'Postal service estimates'),
    ('33173', 'District Post Office - Orange Walk', 1, 'District Post Office - Orange Walk', 'https://mof.gov.bz/wp-content/uploads/2023/04/dpgajbob.pdf', 'Postal service summary block'),
    ('33181', 'District Post Office - Belize', 1, 'District Post Office - Belize', 'https://www.mof.gov.bz/wp-content/uploads/2023/09/5uzn34qr.pdf', 'Postal service estimates'),
    ('33194', 'District Post Office - Cayo', 1, 'District Post Office - Cayo', 'https://mof.gov.bz/wp-content/uploads/2023/04/dpgajbob.pdf', 'Postal service summary block'),
    ('33205', 'District Post Office - Stann Creek', 1, 'District Post Office - Stann Creek', 'https://mof.gov.bz/wp-content/uploads/2023/04/dpgajbob.pdf', 'Postal service summary block'),
    ('33216', 'District Post Office - Toledo', 1, 'District Post Office - Toledo', 'https://mof.gov.bz/wp-content/uploads/2023/04/dpgajbob.pdf', 'Postal service summary block'),
    ('33228', 'District Post Office - Belmopan', 1, 'District Post Office - Belmopan', 'https://www.mof.gov.bz/wp-content/uploads/2023/09/5uzn34qr.pdf', 'Postal service estimates');

DROP TABLE IF EXISTS official_verified_sub_treasuries;
CREATE TABLE official_verified_sub_treasuries (
    sub_treasury_cost_center_code VARCHAR(10) NOT NULL PRIMARY KEY,
    official_name VARCHAR(191) NOT NULL,
    current_exists_in_app TINYINT(1) NOT NULL DEFAULT 0,
    current_app_name VARCHAR(191) DEFAULT NULL,
    district VARCHAR(100) DEFAULT NULL,
    source_url VARCHAR(500) NOT NULL,
    source_note VARCHAR(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO official_verified_sub_treasuries
    (sub_treasury_cost_center_code, official_name, current_exists_in_app, current_app_name, district, source_url, source_note)
VALUES
    ('18041', 'Sub-Treasury - San Pedro', 1, 'Sub Treasury San Pedro', 'Belize', 'https://mof.gov.bz/wp-content/uploads/2023/09/yfqtsrbe.pdf', 'Official sub-treasury cost centre'),
    ('18152', 'Sub-Treasury - Corozal', 1, 'Sub Treasury Corozal', 'Corozal', 'https://www.mof.gov.bz/wp-content/uploads/2023/09/z5ayi3z3.pdf', 'MOF summary / official sub-treasury cost centre'),
    ('18163', 'Sub-Treasury - Orange Walk', 1, 'Sub Treasury Orange Walk', 'Orange Walk', 'https://mof.gov.bz/wp-content/uploads/2023/09/5uzn34qr.pdf', 'Official sub-treasury cost centre'),
    ('18178', 'Sub-Treasury - Belmopan', 1, 'Sub Treasury Belmopan', 'Cayo', 'https://mof.gov.bz/wp-content/uploads/2023/04/vhuitcev.pdf', 'Official sub-treasury cost centre'),
    ('18184', 'Sub-Treasury - San Ignacio', 1, 'Sub Treasury San Ignacio', 'Cayo', 'https://mof.gov.bz/wp-content/uploads/2023/09/ed5ql4bp.pdf', 'Official sub-treasury cost centre'),
    ('18195', 'Sub-Treasury - Dangriga', 1, 'Sub Treasury Dangriga', 'Stann Creek', 'https://mof.gov.bz/wp-content/uploads/2023/09/ed5ql4bp.pdf', 'Official sub-treasury cost centre'),
    ('18206', 'Sub-Treasury - Punta Gorda', 1, 'Sub Treasury Punta Gorda', 'Toledo', 'https://mof.gov.bz/wp-content/uploads/2001/03/qp2pnnaw.pdf', 'Official sub-treasury cost centre');

DROP TABLE IF EXISTS official_verified_activity_items;
CREATE TABLE official_verified_activity_items (
    official_item_code VARCHAR(10) NOT NULL PRIMARY KEY,
    official_item_name VARCHAR(191) NOT NULL,
    current_app_activity_example VARCHAR(191) DEFAULT NULL,
    source_url VARCHAR(500) NOT NULL,
    source_note VARCHAR(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO official_verified_activity_items
    (official_item_code, official_item_name, current_app_activity_example, source_url, source_note)
VALUES
    ('10101', 'Income Tax - PAYE', 'Income Tax - PAYE - 18528 - 10101', 'https://mof.gov.bz/wp-content/uploads/2026/03/DRAFT-ESTIMATES-OF-REVENUE-AND-EXPENDITURE-FY-2026-2027.pdf', 'Official revenue/item code; current app maps this under internal CCA rows'),
    ('10102', 'Income Tax - companies', 'Income Tax - companies - 18528 - 10102', 'https://mof.gov.bz/wp-content/uploads/2026/03/DRAFT-ESTIMATES-OF-REVENUE-AND-EXPENDITURE-FY-2026-2027.pdf', 'Official revenue/item code'),
    ('10103', 'Income Tax - arrears', 'Income Tax - arrears - 18528 - 10103', 'https://mof.gov.bz/wp-content/uploads/2026/03/DRAFT-ESTIMATES-OF-REVENUE-AND-EXPENDITURE-FY-2026-2027.pdf', 'Official revenue/item code'),
    ('10104', 'Income Tax - Contract Tax Withholding', 'Income Tax - Contract Tax Withholding - 18528 - 10104', 'https://mof.gov.bz/wp-content/uploads/2026/03/DRAFT-ESTIMATES-OF-REVENUE-AND-EXPENDITURE-FY-2026-2027.pdf', 'Official revenue/item code'),
    ('10105', 'Income Tax - business tax', 'Income Tax - business tax - 18528 - 10105', 'https://mof.gov.bz/wp-content/uploads/2026/03/DRAFT-ESTIMATES-OF-REVENUE-AND-EXPENDITURE-FY-2026-2027.pdf', 'Official revenue/item code'),
    ('10106', 'Income Tax penalties & Interest', 'Income Tax Interest - 18528 - 10106', 'https://mof.gov.bz/wp-content/uploads/2026/03/DRAFT-ESTIMATES-OF-REVENUE-AND-EXPENDITURE-FY-2026-2027.pdf', 'Official wording differs slightly from current app activity name'),
    ('10107', 'Income Tax penalties', NULL, 'https://mof.gov.bz/wp-content/uploads/2026/03/DRAFT-ESTIMATES-OF-REVENUE-AND-EXPENDITURE-FY-2026-2027.pdf', 'Official revenue/item code; not present as a distinct current activity name'),
    ('10109', 'Administrative Fee BTS', NULL, 'https://mof.gov.bz/wp-content/uploads/2026/03/DRAFT-ESTIMATES-OF-REVENUE-AND-EXPENDITURE-FY-2026-2027.pdf', 'Official revenue/item code; not present as a distinct current activity name'),
    ('10517', 'Belize Broadcasting Authority', 'Belize Broadcasting Authority - 25021 - 10517', 'https://mof.gov.bz/wp-content/uploads/2026/03/DRAFT-ESTIMATES-OF-REVENUE-AND-EXPENDITURE-FY-2026-2027.pdf', 'Official licence/item code'),
    ('10519', 'Registration of Trademarks', 'Registration of Trademarks - 12128 - 10519', 'https://mof.gov.bz/wp-content/uploads/2026/04/APPROVED-ESTIMATES-OF-REVENUE-AND-EXPENDITURE-FY-2026-2027-FINAL.pdf', 'Official royalties / registration item code'),
    ('10605', 'Marriage Licences', 'Marriage - 31017 - 10605', 'https://mof.gov.bz/wp-content/uploads/2026/03/DRAFT-ESTIMATES-OF-REVENUE-AND-EXPENDITURE-FY-2026-2027.pdf', 'Official licence/item code; current app example shortens the label');

DROP TABLE IF EXISTS official_cost_center_activity_catalog;
CREATE TABLE official_cost_center_activity_catalog (
    cost_center_code VARCHAR(10) NOT NULL,
    cost_center_name VARCHAR(191) NOT NULL,
    official_item_code VARCHAR(10) NOT NULL,
    official_item_name VARCHAR(191) NOT NULL,
    current_app_activity_example VARCHAR(191) DEFAULT NULL,
    linked_department_name VARCHAR(191) DEFAULT NULL,
    linked_sub_treasury_name VARCHAR(191) DEFAULT NULL,
    linkage_scope VARCHAR(50) NOT NULL,
    verification_status VARCHAR(50) NOT NULL,
    source_url VARCHAR(500) NOT NULL,
    source_note VARCHAR(255) DEFAULT NULL,
    PRIMARY KEY (cost_center_code, official_item_code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO official_cost_center_activity_catalog
    (cost_center_code, cost_center_name, official_item_code, official_item_name, current_app_activity_example, linked_department_name, linked_sub_treasury_name, linkage_scope, verification_status, source_url, source_note)
VALUES
    ('12017', 'General Registry', '34001', 'Office Supplies', NULL, 'General Registry', NULL, 'cost_center_to_item', 'verified_direct_cost_center', 'https://mof.gov.bz/wp-content/uploads/2023/09/fiyqqkew.pdf', 'Official estimates line-item listing under cost centre 12017.'),
    ('12017', 'General Registry', '34002', 'Books & Periodicals', NULL, 'General Registry', NULL, 'cost_center_to_item', 'verified_direct_cost_center', 'https://mof.gov.bz/wp-content/uploads/2023/09/fiyqqkew.pdf', 'Official estimates line-item listing under cost centre 12017.'),
    ('12017', 'General Registry', '34014', 'Computer Supplies', NULL, 'General Registry', NULL, 'cost_center_to_item', 'verified_direct_cost_center', 'https://mof.gov.bz/wp-content/uploads/2023/09/fiyqqkew.pdf', 'Official estimates line-item listing under cost centre 12017.'),
    ('12017', 'General Registry', '34015', 'Office Equipment', NULL, 'General Registry', NULL, 'cost_center_to_item', 'verified_direct_cost_center', 'https://mof.gov.bz/wp-content/uploads/2023/09/fiyqqkew.pdf', 'Official estimates line-item listing under cost centre 12017.'),
    ('12017', 'General Registry', '34023', 'Printing Services', NULL, 'General Registry', NULL, 'cost_center_to_item', 'verified_direct_cost_center', 'https://mof.gov.bz/wp-content/uploads/2023/09/fiyqqkew.pdf', 'Official estimates line-item listing under cost centre 12017.'),
    ('12017', 'General Registry', '34101', 'Fuel', NULL, 'General Registry', NULL, 'cost_center_to_item', 'verified_direct_cost_center', 'https://mof.gov.bz/wp-content/uploads/2023/09/fiyqqkew.pdf', 'Official estimates line-item listing under cost centre 12017.'),
    ('12017', 'General Registry', '34102', 'Advertisements', NULL, 'General Registry', NULL, 'cost_center_to_item', 'verified_direct_cost_center', 'https://mof.gov.bz/wp-content/uploads/2023/09/fiyqqkew.pdf', 'Official estimates line-item listing under cost centre 12017.'),
    ('12017', 'General Registry', '34103', 'Miscellaneous', NULL, 'General Registry', NULL, 'cost_center_to_item', 'verified_direct_cost_center', 'https://mof.gov.bz/wp-content/uploads/2023/09/fiyqqkew.pdf', 'Official estimates line-item listing under cost centre 12017.'),
    ('12017', 'General Registry', '34106', 'Operating cost - mail delivery', NULL, 'General Registry', NULL, 'cost_center_to_item', 'verified_direct_cost_center', 'https://www.mof.gov.bz/wp-content/uploads/2023/09/z5ayi3z3.pdf', 'Explicitly shown online for General Registry cost centre 12017.'),
    ('12017', 'General Registry', '34604', 'Telephones', NULL, 'General Registry', NULL, 'cost_center_to_item', 'verified_direct_cost_center', 'https://www.mof.gov.bz/wp-content/uploads/2023/09/z5ayi3z3.pdf', 'Explicitly shown online for General Registry cost centre 12017.'),
    ('12128', 'BELIPO', '10519', 'Registration of Trademarks', 'Registration of Trademarks - 12128 - 10519', 'Belize Intellectual Property Office (BELIPO)', NULL, 'cost_center_to_item', 'verified_direct_cost_center', 'https://mof.gov.bz/wp-content/uploads/2026/04/APPROVED-ESTIMATES-OF-REVENUE-AND-EXPENDITURE-FY-2026-2027-FINAL.pdf', 'Official royalties / registration item code.'),
    ('18528', 'Belize Tax Service - Belmopan', '10101', 'Income Tax - PAYE', 'Income Tax - PAYE - 18528 - 10101', 'Belize Tax Service', NULL, 'cost_center_to_item', 'verified_direct_cost_center', 'https://mof.gov.bz/wp-content/uploads/2026/03/DRAFT-ESTIMATES-OF-REVENUE-AND-EXPENDITURE-FY-2026-2027.pdf', 'Official revenue/item code.'),
    ('18528', 'Belize Tax Service - Belmopan', '10102', 'Income Tax - companies', 'Income Tax - companies - 18528 - 10102', 'Belize Tax Service', NULL, 'cost_center_to_item', 'verified_direct_cost_center', 'https://mof.gov.bz/wp-content/uploads/2026/03/DRAFT-ESTIMATES-OF-REVENUE-AND-EXPENDITURE-FY-2026-2027.pdf', 'Official revenue/item code.'),
    ('18528', 'Belize Tax Service - Belmopan', '10103', 'Income Tax - arrears', 'Income Tax - arrears - 18528 - 10103', 'Belize Tax Service', NULL, 'cost_center_to_item', 'verified_direct_cost_center', 'https://mof.gov.bz/wp-content/uploads/2026/03/DRAFT-ESTIMATES-OF-REVENUE-AND-EXPENDITURE-FY-2026-2027.pdf', 'Official revenue/item code.'),
    ('18528', 'Belize Tax Service - Belmopan', '10104', 'Income Tax - Contract Tax Withholding', 'Income Tax - Contract Tax Withholding - 18528 - 10104', 'Belize Tax Service', NULL, 'cost_center_to_item', 'verified_direct_cost_center', 'https://mof.gov.bz/wp-content/uploads/2026/03/DRAFT-ESTIMATES-OF-REVENUE-AND-EXPENDITURE-FY-2026-2027.pdf', 'Official revenue/item code.'),
    ('18528', 'Belize Tax Service - Belmopan', '10105', 'Income Tax - business tax', 'Income Tax - business tax - 18528 - 10105', 'Belize Tax Service', NULL, 'cost_center_to_item', 'verified_direct_cost_center', 'https://mof.gov.bz/wp-content/uploads/2026/03/DRAFT-ESTIMATES-OF-REVENUE-AND-EXPENDITURE-FY-2026-2027.pdf', 'Official revenue/item code.'),
    ('18528', 'Belize Tax Service - Belmopan', '10106', 'Income Tax penalties & Interest', 'Income Tax Interest - 18528 - 10106', 'Belize Tax Service', NULL, 'cost_center_to_item', 'verified_direct_cost_center', 'https://mof.gov.bz/wp-content/uploads/2026/03/DRAFT-ESTIMATES-OF-REVENUE-AND-EXPENDITURE-FY-2026-2027.pdf', 'Official wording differs slightly from current app activity name.'),
    ('18528', 'Belize Tax Service - Belmopan', '10107', 'Income Tax penalties', NULL, 'Belize Tax Service', NULL, 'cost_center_to_item', 'verified_direct_cost_center', 'https://mof.gov.bz/wp-content/uploads/2026/03/DRAFT-ESTIMATES-OF-REVENUE-AND-EXPENDITURE-FY-2026-2027.pdf', 'Official revenue/item code.'),
    ('18528', 'Belize Tax Service - Belmopan', '10109', 'Administrative Fee BTS', NULL, 'Belize Tax Service', NULL, 'cost_center_to_item', 'verified_direct_cost_center', 'https://mof.gov.bz/wp-content/uploads/2026/03/DRAFT-ESTIMATES-OF-REVENUE-AND-EXPENDITURE-FY-2026-2027.pdf', 'Official revenue/item code.'),
    ('25021', 'Belize Broadcasting Authority', '10517', 'Belize Broadcasting Authority', 'Belize Broadcasting Authority - 25021 - 10517', 'Belize Broadcasting Authority', NULL, 'cost_center_to_item', 'verified_direct_cost_center', 'https://mof.gov.bz/wp-content/uploads/2026/03/DRAFT-ESTIMATES-OF-REVENUE-AND-EXPENDITURE-FY-2026-2027.pdf', 'Official licence/item code.'),
    ('31017', 'Attorney General - General Administration', '10605', 'Marriage Licences', 'Marriage - 31017 - 10605', 'Attorney General - General Administration', NULL, 'cost_center_to_item', 'verified_direct_cost_center', 'https://mof.gov.bz/wp-content/uploads/2026/03/DRAFT-ESTIMATES-OF-REVENUE-AND-EXPENDITURE-FY-2026-2027.pdf', 'Official licence/item code; current app example shortens the label.');

DROP TABLE IF EXISTS official_cleaned_departments;
CREATE TABLE official_cleaned_departments (
    current_department_name VARCHAR(191) NOT NULL PRIMARY KEY,
    proposed_department_name VARCHAR(191) DEFAULT NULL,
    action_type VARCHAR(30) NOT NULL,
    source_basis VARCHAR(100) NOT NULL,
    source_url VARCHAR(500) DEFAULT NULL,
    notes VARCHAR(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO official_cleaned_departments
    (current_department_name, proposed_department_name, action_type, source_basis, source_url, notes)
VALUES
    ('Attorney General', 'Attorney General', 'review', 'legacy/current app', NULL, 'Exists in legacy and current app but is inactive in current table; decide whether to keep as parent grouping.'),
    ('Attorney General - General Administration', 'Attorney General - General Administration', 'keep', 'legacy/current app', NULL, 'Current app row aligns with cost centre 31017 grouping.'),
    ('Belize Broadcasting Authority', 'Belize Broadcasting Authority', 'keep', 'official record', 'https://www.mof.gov.bz/wp-content/uploads/2023/09/z5ayi3z3.pdf', 'Supported by official cost-centre and item records.'),
    ('Belize Intellectual Property Office (BELIPO)', 'Belize Intellectual Property Office (BELIPO)', 'keep', 'official record', 'https://www.mof.gov.bz/wp-content/uploads/2023/09/z5ayi3z3.pdf', 'Supported by official cost-centre and item records.'),
    ('Belize Tax Service', 'Belize Tax Service', 'keep', 'official record', 'https://mof.gov.bz/wp-content/uploads/2025/05/Draft-Estimates-of-Revenue-and-Expenditure%E2%80%93Fiscal-Year-2025-2026.pdf', 'Supported by official cost-centre structure.'),
    ('Central Health Region', 'Central Health Region', 'keep', 'official record', 'https://mof.gov.bz/wp-content/uploads/2025/05/Draft-Estimates-of-Revenue-and-Expenditure%E2%80%93Fiscal-Year-2025-2026.pdf', 'Supported by official cost-centre structure.'),
    ('Civil Aviation', 'Department of Civil Aviation', 'rename', 'official record', 'https://mof.gov.bz/wp-content/uploads/2025/05/Draft-Estimates-of-Revenue-and-Expenditure%E2%80%93Fiscal-Year-2025-2026.pdf', 'Current app label is shortened; official cost-centre naming uses Department of Civil Aviation.'),
    ('Corozal Community Hospital', 'Corozal Community Hospital', 'keep', 'official record', 'https://mof.gov.bz/wp-content/uploads/2025/05/Draft-Estimates-of-Revenue-and-Expenditure%E2%80%93Fiscal-Year-2025-2026.pdf', 'Supported by official cost-centre structure.'),
    ('Customs & Excise', 'Customs & Excise', 'keep', 'official record', 'https://www.mof.gov.bz/wp-content/uploads/2023/09/z5ayi3z3.pdf', 'Supported by official cost-centre structure.'),
    ('Fisheries Department', 'Fisheries Department', 'keep', 'official record', 'https://mof.gov.bz/wp-content/uploads/2025/05/Draft-Estimates-of-Revenue-and-Expenditure%E2%80%93Fiscal-Year-2025-2026.pdf', 'Supported by official cost-centre structure.'),
    ('General Registry', 'General Registry', 'keep', 'official record', 'https://www.mof.gov.bz/wp-content/uploads/2023/09/z5ayi3z3.pdf', 'Supported by official cost-centre structure.'),
    ('Immigration Department', 'Immigration Department', 'keep', 'official record', 'https://mof.gov.bz/wp-content/uploads/2025/05/Draft-Estimates-of-Revenue-and-Expenditure%E2%80%93Fiscal-Year-2025-2026.pdf', 'Supported by official cost-centre structure.'),
    ('Judiciary', 'Judiciary', 'keep', 'official record', 'https://www.mof.gov.bz/wp-content/uploads/2023/09/z5ayi3z3.pdf', 'Supported by official court cost-centre structure.'),
    ('Magistrate Court', 'Magistrate Court', 'keep', 'official record', 'https://www.mof.gov.bz/wp-content/uploads/2023/09/z5ayi3z3.pdf', 'Supported by official court cost-centre structure.'),
    ('Ministry of Agriculture, FS&E - General Administration', 'Ministry of Agriculture, Food Security and Enterprise - General Administration', 'rename', 'official record', 'https://mof.gov.bz/wp-content/uploads/2025/05/Draft-Estimates-of-Revenue-and-Expenditure%E2%80%93Fiscal-Year-2025-2026.pdf', 'Expand abbreviation to official portfolio title.'),
    ('Ministry of Education', 'Ministry of Education', 'keep', 'official record', 'https://mof.gov.bz/wp-content/uploads/2025/05/Draft-Estimates-of-Revenue-and-Expenditure%E2%80%93Fiscal-Year-2025-2026.pdf', 'Still matches official ministry naming at a high level.'),
    ('Ministry of Finance - General Administration', 'Ministry of Finance - General Administration', 'keep', 'official record', 'https://www.mof.gov.bz/wp-content/uploads/2023/09/z5ayi3z3.pdf', 'Supported by official cost-centre structure.'),
    ('Ministry of Health', 'Ministry of Health and Wellness', 'rename', 'official record', 'https://mof.gov.bz/wp-content/uploads/2025/05/Draft-Estimates-of-Revenue-and-Expenditure%E2%80%93Fiscal-Year-2025-2026.pdf', 'Current official portfolio title includes Wellness.'),
    ('Ministry of Natural Resources, P&M - General Administration', 'Ministry of Natural Resources, Petroleum and Mining - General Administration', 'rename', 'official record', 'https://mof.gov.bz/wp-content/uploads/2025/05/Draft-Estimates-of-Revenue-and-Expenditure%E2%80%93Fiscal-Year-2025-2026.pdf', 'Expand abbreviation to official portfolio title.'),
    ('Northern Regional Hospital', 'Northern Regional Hospital', 'keep', 'official record', 'https://mof.gov.bz/wp-content/uploads/2025/05/Draft-Estimates-of-Revenue-and-Expenditure%E2%80%93Fiscal-Year-2025-2026.pdf', 'Supported by official cost-centre structure.'),
    ('Police Department', 'Police Department', 'keep', 'official record', 'https://mof.gov.bz/wp-content/uploads/2025/05/Draft-Estimates-of-Revenue-and-Expenditure%E2%80%93Fiscal-Year-2025-2026.pdf', 'Supported by official cost-centre structure.'),
    ('Post Office', 'Post Office', 'keep', 'official record', 'https://www.mof.gov.bz/wp-content/uploads/2023/09/5uzn34qr.pdf', 'Supported by official postal cost-centre structure.'),
    ('San Ignacio Community Hospital', 'San Ignacio Community Hospital', 'keep', 'official record', 'https://mof.gov.bz/wp-content/uploads/2025/05/Draft-Estimates-of-Revenue-and-Expenditure%E2%80%93Fiscal-Year-2025-2026.pdf', 'Supported by official cost-centre structure.'),
    ('Southern Regional Hospital', 'Southern Regional Hospital', 'keep', 'official record', 'https://mof.gov.bz/wp-content/uploads/2025/05/Draft-Estimates-of-Revenue-and-Expenditure%E2%80%93Fiscal-Year-2025-2026.pdf', 'Supported by official cost-centre structure.'),
    ('Test Belize Sub-Treasury', NULL, 'remove', 'current app test row', NULL, 'Local test row; not an official department.'),
    ('Toledo Community Hospital', 'Toledo Community Hospital', 'keep', 'official record', 'https://mof.gov.bz/wp-content/uploads/2025/05/Draft-Estimates-of-Revenue-and-Expenditure%E2%80%93Fiscal-Year-2025-2026.pdf', 'Supported by official cost-centre structure.'),
    ('Transport Department', 'Transport Department', 'keep', 'official record', 'https://mof.gov.bz/wp-content/uploads/2025/05/Draft-Estimates-of-Revenue-and-Expenditure%E2%80%93Fiscal-Year-2025-2026.pdf', 'Supported by official cost-centre structure.'),
    ('Treasury Department', 'Treasury Department', 'keep', 'official record', 'https://www.mof.gov.bz/wp-content/uploads/2023/09/yfqtsrbe.pdf', 'Supported by official Treasury and sub-treasury cost-centre structure.'),
    ('Vital Statistical Unit', 'Vital Statistical Unit', 'keep', 'official record', 'https://mof.gov.bz/wp-content/uploads/2025/05/Draft-Estimates-of-Revenue-and-Expenditure%E2%80%93Fiscal-Year-2025-2026.pdf', 'Supported by official cost-centre structure.'),
    ('Western Regional Hospital', 'Western Regional Hospital', 'keep', 'official record', 'https://mof.gov.bz/wp-content/uploads/2025/05/Draft-Estimates-of-Revenue-and-Expenditure%E2%80%93Fiscal-Year-2025-2026.pdf', 'Corrected legacy typo and aligned to official hospital naming.'),
    ('Elections & Boundaries', 'Elections and Boundaries Department', 'rename', 'official record', 'https://www.mof.gov.bz/wp-content/uploads/2023/09/z5ayi3z3.pdf', 'Normalize current app label to official department naming.'),
    ('National Emergency Management Office', 'National Emergency Management Organization', 'rename', 'official record', 'https://mof.gov.bz/wp-content/uploads/2025/05/Draft-Estimates-of-Revenue-and-Expenditure%E2%80%93Fiscal-Year-2025-2026.pdf', 'Official body is usually named NEMO / Organization.'),
    ('Ministry of Foreign Trade', 'Ministry of Foreign Trade', 'keep', 'official record', 'https://mof.gov.bz/wp-content/uploads/2025/05/Draft-Estimates-of-Revenue-and-Expenditure%E2%80%93Fiscal-Year-2025-2026.pdf', 'Supported by official ministry structure.'),
    ('Ministry of Culture, Youth & Sports', 'Ministry of Culture, Youth, Sports and Diaspora Relations', 'rename', 'official record', 'https://mof.gov.bz/wp-content/uploads/2026/04/APPROVED-ESTIMATES-OF-REVENUE-AND-EXPENDITURE-FY-2026-2027-FINAL.pdf', 'Current official portfolio title is broader than current app label.'),
    ('Ministry of Human Development', 'Ministry of Human Development, Family Support and Gender Affairs', 'rename', 'official record', 'https://mof.gov.bz/wp-content/uploads/2026/04/APPROVED-ESTIMATES-OF-REVENUE-AND-EXPENDITURE-FY-2026-2027-FINAL.pdf', 'Current official portfolio title is broader than current app label.'),
    ('Ministry of Sustainable Development', 'Ministry of Sustainable Development, Climate Change and Solid Waste Management', 'rename', 'official record', 'https://mof.gov.bz/wp-content/uploads/2026/04/APPROVED-ESTIMATES-OF-REVENUE-AND-EXPENDITURE-FY-2026-2027-FINAL.pdf', 'Current official portfolio title is broader than current app label.'),
    ('Ministry of Infrastructure & Housing', 'Ministry of Infrastructure Development and Housing', 'rename', 'official record', 'https://mof.gov.bz/ova_doc/', 'Normalize to current official portfolio wording.'),
    ('Ministry of Public Utilities', 'Ministry of Public Utilities, Energy and Logistics', 'rename', 'official record', 'https://mof.gov.bz/wp-content/uploads/2026/04/APPROVED-ESTIMATES-OF-REVENUE-AND-EXPENDITURE-FY-2026-2027-FINAL.pdf', 'Current official portfolio title is broader than current app label.'),
    ('Ministry of Economic Development', 'Ministry of Economic Transformation and Investment', 'rename', 'official record', 'https://mof.gov.bz/wp-content/uploads/2026/04/APPROVED-ESTIMATES-OF-REVENUE-AND-EXPENDITURE-FY-2026-2027-FINAL.pdf', 'Normalize to current official ministry title.'),
    ('Ministry of Rural Development', 'Ministry of Rural Transformation', 'rename', 'official record', 'https://mof.gov.bz/wp-content/uploads/2026/04/APPROVED-ESTIMATES-OF-REVENUE-AND-EXPENDITURE-FY-2026-2027-FINAL.pdf', 'Current official portfolio wording uses Rural Transformation.'),
    ('Ministry of Local Government & Labour', 'Ministry of Rural Transformation, Community Development, Local Government and Labour', 'rename', 'official record', 'https://mof.gov.bz/wp-content/uploads/2026/04/APPROVED-ESTIMATES-OF-REVENUE-AND-EXPENDITURE-FY-2026-2027-FINAL.pdf', 'Normalize to current official ministry title.'),
    ('Ministry of Energy', 'Ministry of Energy', 'keep', 'official record', 'https://mof.gov.bz/wp-content/uploads/2026/04/APPROVED-ESTIMATES-OF-REVENUE-AND-EXPENDITURE-FY-2026-2027-FINAL.pdf', 'Supported by current official ministry structure.'),
    ('Final Department Test', NULL, 'remove', 'current app test row', NULL, 'Local test row; not an official department.');

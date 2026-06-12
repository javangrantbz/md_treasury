# Master Data Cleanup Plan

Date: 2026-06-01

## Scope

Reviewed:

- Original dump: `C:\Users\Javan\Downloads\md_original.sql`
- Current database: `treasury_connect`
- Public Belize Ministry of Finance estimates and budget summaries

## Current State

- `departments`: 43 rows, 43 unique names
- `cost_centers`: 117 rows, 117 unique codes, 117 unique names
- `cost_center_activities`: 657 rows, 657 unique `activity_code` values, only 9 distinct `activity_name` values
- `sub_treasuries`: 7 rows after cleanup to official Belize sub-treasuries

## What The Original Dump Contains

The original dump does not contain a normalized departments table for this module. It contains:

- `op_cost_centers`
- `op_dep_cost_center`
- `op_bank_accounts`
- `op_dep_banks`

Key result:

- `op_cost_centers` has 240 rows
- but only 117 distinct `costCenterNumber` values
- and 117 distinct `costCenterName` values

That means the legacy dump already contained duplicated cost-center seeds.

## Duplicate Patterns In The Original Dump

Most cost centers appear twice. Some appear more than twice.

Examples:

- `18211` appears 17 times
- `12017` appears 3 times
- `31017` appears 3 times
- many official codes appear exactly 2 times

The worst corruption is that code `18211` was reused for unrelated names:

- `Customs & Excise Belize City`
- `District Post Office - Belize`
- `District Post Office - Belmopan`
- `District Post Office - Cayo`
- `District Post Office - Corozal`
- `District Post Office - Orange Walk`
- `District Post Office - Stann Creek`
- `District Post Office - Toledo`
- `Postal Services - Head Office`

That is not a valid government mapping.

## Current Cost Center Table

The current `cost_centers` table is structurally deduped, but still has quality issues:

- 116 of 117 rows have `department_id = NULL`
- 117 of 117 rows have `sub_treasury_id = NULL`
- one row looks like a local test row: `132323 Test Center`
- five rows look like revenue line items or legacy categories, not cost centres:
  - `00002 Family Maintenance   Family Court`
  - `00004 Contribution Development Concession`
  - `00006 Post Office Accounts`
  - `00008 Civil Actions & Land Titles   Registry`
  - `00010 Liquor licensing fees etc`

These six rows should not be treated as trusted official cost-centre master data until verified.

## Current Activity Table

The current `cost_center_activities` table should not be treated as canonical government activity data.

Evidence:

- all 657 rows use internal codes in the format `CCA-...`
- there are only 9 distinct activity names across 657 rows
- activity names are repeated across unrelated cost centres

Examples of clear bad mappings:

- `18017 Ministry of Finance - General Administration` has dozens of rows all named `Registration of Trademarks - 12128 - 10519`
- `18211 Customs & Excise Belize City` contains rows named `Marriage - 31017 - 10605` and `Income Tax - PAYE - 18528 - 10101`

This strongly suggests the activity table was generated from a flawed migration or cartesian-style import.

## What Matches Official Government Records

The `cost_centers.code` field mostly aligns with official Belize government cost-centre codes published in Ministry of Finance estimates.

Verified examples include:

- `18017` Ministry of Finance - General Administration
- `18071` Treasury entry for Belize City / Treasury head office
- `18152` Sub-Treasury Corozal
- `18163` Sub-Treasury Orange Walk
- `18178` Sub-Treasury Belmopan
- `18184` Sub-Treasury San Ignacio
- `18195` Sub-Treasury Dangriga
- `18206` Sub-Treasury Punta Gorda
- `18528` Belize Tax Service - Belmopan
- `30261` Immigration Services - Belize City
- `33162` District Post Office - Corozal

Official sources used for verification:

- 2025/2026 draft estimates:
  `https://mof.gov.bz/wp-content/uploads/2025/05/Draft-Estimates-of-Revenue-and-Expenditure%E2%80%93Fiscal-Year-2025-2026.pdf`
- 2026/2027 approved estimates:
  `https://mof.gov.bz/wp-content/uploads/2026/04/APPROVED-ESTIMATES-OF-REVENUE-AND-EXPENDITURE-FY-2026-2027-FINAL.pdf`
- Ministry of Finance budget summaries and historical estimates:
  `https://mof.gov.bz/wp-content/uploads/2023/09/z5ayi3z3.pdf`
  `https://mof.gov.bz/wp-content/uploads/2023/04/3clmh1ns.pdf`

## What Can Be Safely Reused

Safe or mostly safe:

- official-looking 5-digit `cost_centers.code` values
- matching `cost_centers.name` values that align with government estimates
- the cleaned `sub_treasuries` table using official Treasury cost-centre codes
- the current `departments` table as an application-owned structure, after removing test rows

Unsafe or needs rebuild:

- `cost_center_activities`
- any mapping derived directly from `op_dep_cost_center`
- `cost_centers` rows with codes `00002`, `00004`, `00006`, `00008`, `00010`, `132323`

## Recommended Cleanup Direction

### Phase 1

Create a canonical cost-center reference file from the current unique `cost_centers` rows, excluding:

- test rows
- non-5-digit rows
- unverified special rows

### Phase 2

Add an explicit manual mapping file for:

- `cost_center_code`
- `cost_center_name`
- `department_id`
- optional `sub_treasury_id`
- verification status
- source document / page reference

### Phase 3

Rebuild `cost_center_activities` from authoritative revenue/item schedules, not from the current `CCA-...` table.

Recommended canonical activity shape:

- official item code
- official item name
- owning cost center code
- revenue class / program code if applicable
- source document reference

### Phase 4

Apply cleanup migrations only after the canonical mapping file is reviewed.

## Immediate Next Step

Build a reviewed staging file in the repo with:

- all current 117 cost centers
- a `keep / review / drop` flag
- target `department_id`
- target `sub_treasury_id`
- source note

That is the right bridge from the current messy state to a clean migration.

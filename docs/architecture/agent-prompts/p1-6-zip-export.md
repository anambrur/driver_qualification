# P1-6: ZIP export per driver and per company (task 1.4)

```
Read docs/architecture/agent-prompts/README.md (rules and AUTO-FINISH), CLAUDE.md,
08-database-schema.md §12 (exports) and §10 (audit: exports and ZIP downloads), 10-workflows.md §9,
12-performance-plan.md §3 (exports and ZIP, queues) and §5 (200-driver company: under 5 minutes, flat memory),
14-open-questions.md §2 Q8 and Q2, 02-requirement-matrix.md row 2.5.
Skills: queue-and-jobs, multi-tenant-safety, security-checklist, db-performance, pest-testing.
Task: Phase 1 task 1.4. Package name: p1-6-zip-export. Requires P1-4 and P1-5 on main.

Build:
1. exports migration and model as 08 §12 (all enum types now; only zip_driver and zip_company are used).
2. Export service request(type, params): permission exports.create, feature zip_export; if the same scope is
   already queued or running, return that export; insert a queued row; dispatch BuildExport after commit;
   audit the request.
3. BuildExport job (exports queue, TenantAware, unique per company, timeout and size cap):
   - read rows in chunks; stream each file into ZipArchive (a temporary local copy per file when the disk is S3);
   - store the ZIP under companies/{id}/exports/ with the P1-5 generated-file method;
   - mark ready with expires_at = now + 7 days; notify the requester; on failure set failed and the error;
     always clean up temporary files.
   Contents (Q8: everything attached to the entity):
   - driver: typed compliance documents, driver_documents files, licence and medical files, folder documents
     (PDF and original);
   - company: every driver (terminated included), every vehicle and trailer (compliance documents, service
     documents, folder documents, photo), and the company-level folders.
   Layout like Drivers/<Last, First> (#id)/<folder or document type>/<file>; names sanitised, duplicates suffixed.
4. Download: authorized route (exports.download, same company, ready, not expired); audit every download.
5. An "Exports" page (type, scope, status, size, expiry, download), a "Download ZIP" button on the driver page
   and on the company Documents page.
6. Scheduled exports:prune (daily): mark expired, delete the file.
7. Tests: permission, feature, request reuse; the ZIP holds the expected paths (open it with ZipArchive);
   another company's export is 404 and its files never appear; a read_only company CAN export (Q2: DQ files
   must stay producible; exports.create is not a write permission [V PermissionCatalogue.php:80]);
   expiry; failure path; audit rows; a perf test in an excluded group (like PermissionScaleTest) with a
   200-driver company: under 5 minutes and a bounded memory_get_peak_usage, run on MySQL.

PENDING, recommended defaults:
- Do export ZIPs count toward storage_gb? Recommend no (temporary files; a nearly full company must still be
  able to export its DQ files). Skip the limit and the counter for the exports area and document it.
- Size cap per ZIP: recommend 2 GB, fail with a clear message above it (splitting is a follow-up).
- Soft-deleted documents: recommend excluded. Terminated drivers: included.
- Notification: recommend database notification + email (MailIdentity).
- Who may download: recommend anyone in the company with exports.download (audited), not only the requester.

Acceptance (13, Phase 1): a company ZIP of 200 drivers builds within the target time.
Plan mode first; wait for approval. Then run AUTO-FINISH from the README.
```

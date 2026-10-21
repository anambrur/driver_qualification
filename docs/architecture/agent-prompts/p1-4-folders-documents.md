# P1-4: Folders and documents, upload to a folder (task 1.2)

```
Read docs/architecture/agent-prompts/README.md (rules and AUTO-FINISH), CLAUDE.md,
08-database-schema.md §1, §2 and §8 (stored_files, folders, documents), 09-erd-audit.md F18,
10-workflows.md §7 and §8, 05-plans-features-entitlements.md §5 (storage limit), 03-multi-tenant-architecture.md,
12-performance-plan.md §3, 14-open-questions.md §2 Q8, 02-requirement-matrix.md rows 2.1–2.3.
Skills: multi-tenant-safety, repository-service-pattern, db-performance, security-checklist,
laravel-conventions, seo-checklist, pest-testing.
Task: Phase 1 task 1.2. Package name: p1-4-folders-documents.
This is a large package: plan it as three commits on one branch (schema and services; UI; integrity and tests).

Build:
1. Migrations for folders and documents exactly as 08 §8: cFKs, the generated parent_key (CASE WHEN, works on
   both engines), unique (company_id, parent_key, name), the "at most one binding / subject" CHECKs through
   CheckConstraints::add(), RESTRICT on CHECK columns, soft deletes, indexes.
2. Models Folder and Document (BelongsToCompany, SoftDeletes) and factories.
3. Folder service:
   - System folders: one per driver (DQ file), vehicle and trailer, created in the same transaction as the
     entity (every create path: driver create, applicant hire, vehicle and trailer create, asset transfer target),
     plus an idempotent command folders:ensure-system for existing rows.
   - Custom folders: create, rename, delete; nesting up to 5 levels; a child gets its parent's binding;
     system folders can't be renamed or deleted; sibling names unique.
4. Document service: upload one or more files into a folder (StoredFileService area documents, storage limit),
   subject taken from the folder binding (F18 rule), optional title, document type, issued and expiry dates;
   download behind authorization (audit DQ downloads); delete = soft delete.
5. UI: a "Documents" tab on the driver, vehicle and trailer pages (that entity's folders), and a company
   "Documents" page in the sidebar. Folder tree, breadcrumb, paginated document table, upload dialog, new /
   rename / delete dialogs. The entity's typed compliance documents appear as read-only virtual entries in its
   system folder, linking to the existing pages. Feature document_folders; permissions folders.view,
   folders.manage, documents.upload, documents.delete.
6. integrity:check: folder deeper than 5, child binding different from its parent, document subject not matching
   its folder binding.
7. Tests: nested custom folders (acceptance), depth limit, sibling names, system folder locked, binding
   inheritance, upload stores the file and raises storage_bytes, storage limit returns 422, isolation for every new
   route (IsolationMatrixTest fixtures), read-only company, feature off hides menu and refuses routes, query budget
   (tests/Support/Perf.php) for the folder page, audit rows, folders:ensure-system is idempotent.

PENDING, recommended defaults:
- Subject and binding columns for tables that come later (trip_id, trip_event_id, fuel_purchase_id, expense_id,
  inspection_id): create the nullable columns now without FKs, and add the FKs in Phase 2–4 with MySQL-only
  migrations (the owner-FK precedent), because altering the table later on SQLite drops its CHECKs.
- Deleting a folder: the unique index also covers soft-deleted rows. Recommend: only an empty folder (no
  children, no documents, trashed included) can be deleted, and it is force-deleted so the name is free again.
- Standard sub-folders inside the DQ file (Application, Licence, Medical, MVR, Drug and alcohol, ...)?
  Recommend none for now (the typed documents already show as virtual entries); ask the client.
- Allowed upload types and size: recommend pdf, jpg, jpeg, png, webp, doc, docx, xls, xlsx, csv, txt; 20 MB each;
  HEIC refused with a clear message.
- Move: recommend moving documents between folders of the same binding only; no folder move in this package.
- Asset Transfer: also copy the asset's folder documents to the target (PO-08 "full history")? Recommend yes,
  through StoredFileCopier, in this package.

Plan mode first; wait for approval. Then run AUTO-FINISH from the README.
```

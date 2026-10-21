# P1-5: Uploaded photo becomes a PDF (task 1.3)

```
Read docs/architecture/agent-prompts/README.md (rules and AUTO-FINISH), CLAUDE.md,
08-database-schema.md §8 (stored_files.converted_from_file_id, documents.original_file_id),
10-workflows.md §7 step 1, 12-performance-plan.md §3 (image to PDF, queues), 14-open-questions.md §2 Q8,
02-requirement-matrix.md row 2.4. Skills: queue-and-jobs, multi-tenant-safety, security-checklist, pest-testing.
Task: Phase 1 task 1.3. Package name: p1-5-image-to-pdf. Requires P1-4 on main.

Build:
1. StoredFileService: a method that stores generated content (same path convention, checksum, size,
   converted_from_file_id) and counts storage like store(). Leave store() unchanged. P1-6 reuses it for ZIPs.
2. Job ConvertImageToPdf (TenantAware, carries companyId and documentId, unique per document, retries with
   backoff, queue per 12 §3): load the image, fix EXIF orientation, cap the pixel size before rendering, render one
   page with dompdf (Letter, orientation from the aspect ratio, fit inside margins), store the PDF, then in one
   transaction set documents.file_id = the PDF and original_file_id = the image. On final failure the document
   keeps the image and the failure is logged.
3. Dispatch after commit from the document upload for jpg, png and webp. Folder uploads only; the typed
   compliance uploads do not change (Q8).
4. Platform capability switch (05 §2): config/documents.php convert_images (env), default on.
5. UI: the document row opens the PDF; an "Original image" download when original_file_id is set.
6. integrity:check: the PDF's converted_from_file_id equals the document's original_file_id.
7. Tests: upload dispatches (Queue::fake); running the job gives a valid PDF (starts with %PDF), keeps the
   original, storage_bytes includes both; EXIF-rotated fixture; oversized image is scaled down; a PDF upload
   does not dispatch; the job is a no-op for a deleted document; tenant context inside the job; failure leaves the image.

PENDING, recommended defaults:
- Storage counts both the image and the PDF? Recommend yes (both are stored).
- Original deletable on its own? Recommend no; deleting the document removes both.
- Show a "Converting" state? Recommend no status column; the image shows until the PDF replaces it.
- Pixel cap: recommend longest side 3000 px.
- [A] Production needs gd or imagick, exif and a queue worker; put them in the deploy steps.

Plan mode first; wait for approval. Then run AUTO-FINISH from the README.
```

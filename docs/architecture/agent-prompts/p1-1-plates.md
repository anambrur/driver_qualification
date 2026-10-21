# P1-1: Licence plate and photo on vehicles and trailers (task 1.5)

```
Read docs/architecture/agent-prompts/README.md (rules and AUTO-FINISH), CLAUDE.md,
08-database-schema.md §7 (vehicles, trailers) and §8 (stored_files), 02-requirement-matrix.md row 3.5,
12-performance-plan.md §3 (files). Skills: laravel-conventions, multi-tenant-safety, security-checklist, seo-checklist.
Task: Phase 1 task 1.5. Package name: p1-1-plates.

Already there [V]: plate_number, plate_state_code, photo_file_id and the photo cFK in the vehicles and
trailers create migrations; AssetTransferService copies the photo. Nothing else uses them. Build:
1. Validation in the vehicle and trailer requests: plate_number (nullable, max 20, trimmed, upper-case),
   plate_state_code (validated against states.code, US and Canada), photo (nullable image).
2. Photo upload through StoredFileService (areas vehicles / trailers) inside the save transaction.
   Replacing or removing a photo purges the old file with purgeWhenUnreferenced.
   Serve the photo through an authorized route (the fleet view permission), never a public URL.
3. Create and edit forms (x-ui fields, state select), the vehicle and trailer yajra tables (plate column,
   searchable), and the detail pages (plate, state, photo thumbnail).
4. Tests: validation, upload / replace / remove, storage_bytes goes up and down, photo route 404 for
   another company (IsolationMatrixTest fixture), table JSON has the plate, read-only company cannot upload.

PENDING, decide in the plan with these recommended defaults:
- Plate unique per company? Recommend no constraint (plates get reissued; same number in two states).
- Plate state required when a plate number is given? Recommend yes.
- Photo types and size: recommend jpg, png, webp, max 5 MB, no PDF conversion.
- Show the plate elsewhere (asset groups, fleet compliance)? Recommend lists and details only, as in the roadmap.

Acceptance (13, Phase 1): plates appear on the asset lists and details.
Plan mode first; wait for approval. Then run AUTO-FINISH from the README.
```

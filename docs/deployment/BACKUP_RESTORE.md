# Backup and restore

## Backup scope

Back up the SQL database, `backend/storage/app/prescriptions`, `backend/storage/app/public`, and the production environment/secrets through the hosting provider's encrypted secret backup. Imports and logs may follow a shorter retention policy. Never place backups in a public web directory.

Use encrypted, access-controlled backups with one daily copy stored separately from the production host. A practical baseline is 7 daily, 4 weekly, and 12 monthly recovery points. Review retention against the pharmacy's legal and privacy obligations before launch.

## Verification

At least monthly, restore the newest backup into an isolated environment, run `php artisan migrate:status`, verify record counts, open several media assets, and have an authorised reviewer verify a sample prescription file. Record the recovery time and any missing data.

Define and approve recovery targets before launch. A reasonable starting point is an RPO of 24 hours and an RTO of 4 hours, but ARK med must choose targets appropriate to its operations and legal obligations.

Every backup job should record its start time, completion status, encrypted archive size, database row-count summary and off-host copy status. Alert an operator when any stage fails.

## Restore sequence

1. Stop writes by enabling maintenance mode.
2. Preserve the failed system for investigation; do not overwrite the only copy.
3. Restore the database and storage from the same recovery point.
4. Restore production secrets securely and deploy the matching application version.
5. Run integrity checks and `/api/v1/health/ready` before reopening traffic.
6. Rotate credentials if compromise is suspected, then document the incident and recovery point.

## Restore acceptance checks

- `php artisan app:production-check` passes.
- Database migrations match the deployed release.
- Active medicine, order, enquiry and prescription counts are plausible.
- Public media loads while prescription and import paths remain inaccessible from the web.
- An authorised reviewer can open a restored prescription and the access is audited.
- A test order can be created, tracked and advanced by authorised staff.
- Queue processing and the scheduler are running.
- Restored backups are securely destroyed after the exercise.

Prescription data is sensitive health information. Limit backup and restore access to named authorised operators and retain an access trail.

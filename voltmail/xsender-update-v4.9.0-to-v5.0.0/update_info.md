# XSender update package

From version : v4.9.0
To version   : v5.0.0
Built on     : 2026-08-28 06:41:15

## What to do as the customer

1. Backup your install + database before applying this update.
2. Upload the contents of this ZIP into your existing XSender install,
   overwriting existing files. Do NOT delete files that aren't in this ZIP
   — those are folders we deliberately preserve (addons, storage, vendor
   when unchanged, etc.).
3. Visit `/admin/system/update` to apply the bundled migrations.
4. If anything looks off post-update, visit `/repair.php?token=xsender-repair`
   to wipe bootstrap/cache + reset OPcache. It self-deletes after run.

## Vendor included in this update

No — composer.lock is unchanged from v4.9.0; your existing vendor/ is reused.

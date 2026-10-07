PisoWiFi rebuilt UI
====================
Replace the existing files in:
C:\xampp\htdocs\arnold_jackies_store\pisowifi\

This rebuild keeps the existing CRUD/history endpoints and adds:
- status.php (AJAX active/inactive status update)
- save_earning.php (AJAX monthly earnings save/update)

The visual layout is rebuilt to match the Computer Shop / Pisonet UI:
- 4 KPI cards
- box card grid
- right-side status summary
- monthly collection panel
- monthly earnings table
- custom modals and toast feedback

No manual MySQL setup is required beyond the existing tables; index.php also safely creates the
pisowifi / pisowifi_earnings tables if they do not already exist.

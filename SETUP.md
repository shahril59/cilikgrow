# CilikGrow - Setup (XAMPP)

1. Copy this folder to `htdocs/cilikgrow/` (keep `config/db.php` and `img/logo.png`).
2. Start Apache and MySQL in XAMPP.
3. Open phpMyAdmin > Import > choose `cilikgrow_fresh.sql` > Go. It drops and recreates `cilikgrow_db` (safe to re-run).
4. Make sure `fix_pass.php` is NOT in the folder (it is no longer needed).
5. Open http://localhost/cilikgrow/

## Demo accounts

| Role       | Username    | Password      | Linked to                  |
|------------|-------------|---------------|----------------------------|
| Management | roseliza    | roseliza123 | Puan Roseliza (Headmaster) |
| Teacher    | cikgufarah  | cikgufarah123    | Cikgu Farah                |
| Parent     | adamrayyan  | adamrayyan123     | Student ID 1 - Adam Rayyan |
| Parent     | nuraleesya  | nuraleesya123  | Student ID 2 - Nur Aleesya |
| Parent     | haziqdanial | haziqdanial123    | Student ID 3 - Haziq Danial |

The database starts with 13 students and no attendance or progress records.

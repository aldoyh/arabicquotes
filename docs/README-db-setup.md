# Database Setup Instructions

To initialize the quotes database, run:

```bash
./start.sh
```

This will create the SQLite database file `quotes.db` in the root directory with the correct schema and some initial quotes.

If you need to recreate the database from scratch, you can delete the existing `quotes.db` file and run `db-update.sh` again.
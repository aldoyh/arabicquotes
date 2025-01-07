# Initial Setup Instructions

1. First, create and initialize the database:
```bash
./start.sh
```

2. Verify the database was created:
```bash
ls -l quotes.db
```

3. Run the application:
```bash
php index.php
```

If you need to recreate the database at any time, simply delete quotes.db and run db-update.sh again:
```bash
rm -f quotes.db
./db-update.sh
```
<?php
// DEPRECATED: Use fetch-quotes.sh with fetch-all-quotes.php, fetch-goodreads-quotes.php, and fetch-wikimedia-quotes.php.
// This file is kept to avoid breaking existing references but intentionally does nothing.

fwrite(STDERR, "fetch-comprehensive-quotes.php is deprecated.\n");
fwrite(STDERR, "Please run: ./fetch-quotes.sh and choose your sources.\n");
exit(1);

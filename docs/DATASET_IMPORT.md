# Dataset Import

Source examined and imported:

- Hugging Face dataset: `HeshamHaroon/arabic-quotes`
- Resource: `https://huggingface.co/datasets/HeshamHaroon/arabic-quotes/sql-console/TYkhdFE`
- Raw JSONL: `https://huggingface.co/datasets/HeshamHaroon/arabic-quotes/resolve/main/arabic_Q.jsonl`

The dataset contains Arabic quote rows with:

- `quote`
- `author`
- `tags`

## Import Command

```bash
php scripts/import-huggingface-quotes.php
```

The importer streams the JSONL file, normalizes quote and author text, inserts rows into `assets/QuotesDB.db`, skips duplicates, and regenerates `assets/quotes.json`.

## Duplicate Handling

Duplicates are prevented by a normalized key pair:

- `quote_key`
- `author_key`

Normalization removes HTML, excess whitespace, Arabic diacritics, punctuation, and common Arabic letter variants before hashing. Existing duplicate rows are merged before the unique index is created.

## Latest Import Result

Imported on `2026-05-15`:

- Rows read: `3,778`
- Inserted: `3,740`
- Skipped duplicates: `38`
- Invalid rows: `0`
- Total database quotes after import: `4,235`
- Distinct displayed authors after import: `1,209`

## Selection Counter

The daily updater selects randomly from the least-used quotes:

```sql
SELECT *
FROM quotes
WHERE hits = (SELECT MIN(hits) FROM quotes)
ORDER BY RANDOM()
LIMIT 1;
```

After selection, `hits` is incremented and the published daily card displays the updated count as `اختيرت N مرة`.

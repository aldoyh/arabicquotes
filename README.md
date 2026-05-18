# مقولات من خيرة العرب

واجهة عربية ثابتة لعرض اقتباس يومي وأرشيف قابل للبحث، جاهزة للنشر على GitHub Pages وhere.now.

## اقتباس اليوم
<!-- QUOTE:START -->

# إذا دعوت الله فلا تستعجل ، و بالغ في الدعاء ، فإذا كنت راضياً بقدر الله منتظراً لفرجه فسيأتيك نصر الله لا محالة .. إذا كنت قانطاً مستعجلاً فأنت لم تنجح في اختبارك و صبرك .. و اعلم أنه يبتـليك بالتأخير لتـحارب وسوسة إبليس .

- ابن القيم

<!-- QUOTE:END -->

## البيانات

- قاعدة البيانات: `assets/QuotesDB.db`
- التصدير الثابت: `assets/quotes.json`
- إجمالي الاقتباسات بعد الاستيراد: `4,235`
- مصدر الاستيراد الجديد: [`HeshamHaroon/arabic-quotes`](https://huggingface.co/datasets/HeshamHaroon/arabic-quotes)

استيراد البيانات:

```bash
php scripts/import-huggingface-quotes.php
```

تحديث اقتباس اليوم وتصدير JSON:

```bash
php .github/scripts/update-quote.php
php -r "require 'inc/db-utils.php'; exportQuotesToJson();"
```

بناء نسخة النشر النظيفة:

```bash
php scripts/build-site.php _site
```

## النشر

- GitHub Pages: `.github/workflows/deploy-pages.yml`
- here.now: `.github/workflows/deploy-herenow.yml`

يتطلب نشر here.now الدائم إضافة secret باسم `HERENOW_API_KEY`. يمكن ضبط repository variable باسم `HERENOW_SLUG` لتحديث موقع here.now قائم.

## التوثيق

- [استيراد البيانات](docs/DATASET_IMPORT.md)
- [النشر](docs/DEPLOYMENT.md)
- [صفحة التوثيق المنشورة](docs/index.html)

# مقولات من خيرة العرب

واجهة عربية ثابتة لعرض اقتباس يومي وأرشيف قابل للبحث، جاهزة للنشر على GitHub Pages وhere.now.

## الموقع الحي

<!-- HERENOW_URL:START -->
🌐 [stellar-silence-mj8t.here.now](https://stellar-silence-mj8t.here.now/)
<!-- HERENOW_URL:END -->

## اقتباس اليوم
<!-- QUOTE:START -->

# الفشل هو مجموعة التجارب التي تسبق النجاح.

- طاغور

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

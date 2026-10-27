# مقولات من خيرة العرب

واجهة عربية ثابتة لعرض اقتباس يومي وأرشيف قابل للبحث، جاهزة للنشر على GitHub Pages وhere.now.

## الموقع الحي

<!-- HERENOW_URL:START -->
🌐 [gentle-hollow-zwks.here.now](https://gentle-hollow-zwks.here.now/)
<!-- HERENOW_URL:END -->

## اقتباس اليوم
<!-- QUOTE:START -->

# يجب أن أحذركم أنه إذا كانت تصريحاتي واضحة بالنسبة لكم ، فلا بد أنكم قد أسأتم فهمها .

- آلان غرينسبان

<!-- QUOTE:END -->

## البيانات وقاعدة البيانات

- قاعدة البيانات المحلية (SQLite): `assets/QuotesDB.db` (مستبعدة من نظام التتبع Git لتفادي تضخم المستودع)
- التصدير الثابت ومصدر الحقيقة للبيانات: `assets/quotes.json`
- نظام الهجرات التلقائي: `database/migrations/`
- إجمالي الاقتباسات: `4,235`
- مصدر الاستيراد: [`HeshamHaroon/arabic-quotes`](https://huggingface.co/datasets/HeshamHaroon/arabic-quotes)

تشغيل الهجرات وبناء قاعدة البيانات محلياً:

```bash
composer run migrate -- --seed
# أو فحص حالة الهجرات
composer run db:status
# أو إعادة بناء وتعبئة قاعدة البيانات بالكامل
composer run db:fresh
```

استيراد البيانات وتحديثها:

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

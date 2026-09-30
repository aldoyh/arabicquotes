# مقولات من خيرة العرب

واجهة عربية ثابتة لعرض اقتباس يومي وأرشيف قابل للبحث، جاهزة للنشر على GitHub Pages وhere.now.

## الموقع الحي

<!-- HERENOW_URL:START -->
🌐 [gentle-hollow-zwks.here.now](https://gentle-hollow-zwks.here.now/)
<!-- HERENOW_URL:END -->

## اقتباس اليوم
<!-- QUOTE:START -->

# كفران النعمة يجعل عاقبتها شقاءً و بلاءً ، مع أن المنعم عليه يستمتع بها إلى أجل . و الصبر على المصيبة يجعل عاقبتها ثواباً و تفريجاً مع أن المصاب بها ناله شئ من الضرر ، و بما أن العاقبة في الرؤية الإسلامية هي كل شيء ؛ فان الموقف من أحداث الحياة المختلفة والتي يقرر عواقبها يعد هو القضية الجوهرية التي تستحق أعظم العناية .

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

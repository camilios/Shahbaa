# Shahbaa Stripe — Postman

## الاستيراد

1. استورد `Shahbaa-Stripe-Test.postman_collection.json`.
2. استورد `Shahbaa-Stripe-Test.postman_environment.json` واختر البيئة.
3. لا تضع `STRIPE_SECRET` في Postman مطلقًا.
4. اترك `webhook_test_secret` فارغًا عند اختبار Railway. استخدمه فقط مع سر Webhook تجريبي محلي منفصل.

## التسلسل

شغّل المجلدات بالترتيب: `00` ثم `01` ثم `02` ثم `03`. قبل إنشاء الحجز، اختر رحلة مستقبلية قابلة للحجز وضع معرف الرحلة ومعرفي محطتيها في Environment.

بعد إنشاء Checkout افتح قيمة `checkout_url` في المتصفح:

1. جرّب أولًا البطاقة المرفوضة `4000 0000 0000 0002` وتأكد أن الحجز بقي `pending/unpaid`.
2. أعد المحاولة في الجلسة نفسها بالبطاقة الناجحة `4242 4242 4242 4242`، تاريخ مستقبلي، وأي CVC من ثلاث خانات.
3. لا تستخدم بيانات بطاقة حقيقية.

اختبارات Webhook الموقعة في المجلد `04` مخصصة لبيئة محلية أو سر Test Mode فقط. اختبار Railway الحقيقي الأفضل يتم عبر Stripe Test Dashboard باستخدام **Resend event**، لأن سر endpoint يجب ألا يُنسخ أو يُشارك دون حاجة.

بعد الدفع شغّل `03.3 Get booking after webhook` ثم `03.4 Verify idempotency after Stripe resend` بعد إعادة الحدث من لوحة Stripe. يجب بقاء `paid_at` ثابتًا وعدم تكرار الإشعار.

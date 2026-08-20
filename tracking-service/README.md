# Shahbaa Realtime Tracking Service

خدمة مستقلة تستقبل موقع الحافلة من تطبيق السائق وتبث آخر موقع فورياً إلى الأدمن المشترك في الرحلة. Laravel هو مصدر الحقيقة للمستخدم والرحلة والصلاحيات، ولا تتصل هذه الخدمة بقاعدة بيانات Laravel.

## تدفق البيانات

```text
Driver GPS -> Socket.IO -> Node tracking service -> trip:{id}:admins -> Admin dashboard
                         -> Laravel authorization API
```

- السائق مصدر GPS فقط ولا تعرض له هذه الميزة خريطة مراقبة.
- الأدمن هو الطرف الوحيد الذي يشترك في موقع الرحلة.
- Laravel يفوض كل `trip:subscribe` و`location:publish:start` باستخدام Sanctum Bearer Token ومفتاح خدمة مشترك.
- تعيد الخدمة التحقق دورياً من صلاحيات الناشر والمشتركين، وتوقف وصولهم إذا أُلغي التوكن أو تغيّرت حالة المستخدم أو الرحلة.
- لا يُحفظ سجل مسار. يحتفظ `MemoryLocationStore` بآخر نقطة حتى انتهاء TTL، بما في ذلك بعد توقف السائق أو انقطاعه.

## المتطلبات والتشغيل

- PHP 8.2 وLaravel 12 للمشروع الأساسي.
- Node.js 20 LTS أو أحدث.

في إعداد Laravel أضف إلى `.env`:

```env
TRACKING_SERVICE_SECRET=use-the-same-long-random-secret
TRACKING_TRACKABLE_TRIP_STATUSES=active
```

لا تضع قيمة السر الحقيقية في Git. الحالة الوحيدة القابلة للتتبع افتراضياً هي `active`، لأنها الحالة المستخدمة حالياً للرحلة الجارية في المشروع.

ثم شغّل:

```bash
php artisan serve
cd tracking-service
cp .env.example .env
npm install
npm run dev
```

عدّل `.env` في الخدمة واجعل `TRACKING_SERVICE_SECRET` مطابقاً لقيمة Laravel. المنفذ الافتراضي `4000`، وLaravel الافتراضي `http://127.0.0.1:8000`.

للتحقق الصحي:

```http
GET http://127.0.0.1:4000/health
```

## Endpoint التفويض في Laravel

```http
POST /api/v1/realtime/authorize
Authorization: Bearer <sanctum-token>
X-Tracking-Service-Key: <shared-secret>
Content-Type: application/json

{"trip_id": 1, "action": "publish"}
```

- `publish`: مستخدم `active`، دوره `driver`، وهو `trips.driver_id` للرحلة.
- `subscribe`: مستخدم `active` ودوره `admin`.
- في الحالتين يجب أن تكون حالة الرحلة ضمن `TRACKING_TRACKABLE_TRIP_STATUSES`.

## أحداث Socket.IO

كل acknowledgements الناجحة تبدأ بـ`{ "ok": true }`. الفشل يعيد `{ "ok": false, "error": { "code", "message" } }` ويبث أيضاً `tracking:error`.

### اتصال العميل

```js
const socket = io('http://127.0.0.1:4000', {
  auth: { token: laravelSanctumToken },
  transports: ['websocket'],
  reconnection: true,
});
```

الاتصال دون توكن يُرفض. التوكن لا يُحلل داخل Node.js ولا يُسجل في logs.

### السائق

ابدأ النشر بعد اتصال أو إعادة اتصال:

```js
socket.emit('location:publish:start', { tripId: 1 }, console.log);
```

بعد نجاح التفويض، أرسل كل 10–15 ثانية. لا ترسل `tripId` أو `driverId` داخل التحديث:

```js
socket.emit('location:update', {
  latitude: 32.7102,
  longitude: 36.5651,
  speed: 62.5,
  heading: 175,
  accuracy: 10,
  recordedAt: new Date().toISOString(),
}, console.log);
```

عند إنهاء إرسال GPS:

```js
socket.emit('location:publish:stop', {}, console.log);
```

### الأدمن

```js
socket.emit('trip:subscribe', { tripId: 1 }, console.log);
socket.on('trip:location', (location) => moveMapMarker(location));
socket.on('tracking:status', ({ status, lastUpdateAt }) => updateStatus(status, lastUpdateAt));

// عند مغادرة صفحة الرحلة
socket.emit('trip:unsubscribe', { tripId: 1 }, console.log);
```

عند الاشتراك ترسل الخدمة آخر موقع غير منتهٍ مباشرة، ثم تحديثات بالشكل:

```json
{
  "tripId": 1,
  "latitude": 32.7102,
  "longitude": 36.5651,
  "speed": 62.5,
  "heading": 175,
  "accuracy": 10,
  "recordedAt": "2026-07-21T10:00:00.000Z",
  "receivedAt": "2026-07-21T10:00:01.000Z",
  "trackingStatus": "online"
}
```

## التحقق والحالات

- الإحداثيات ضمن الحدود الجغرافية، والسرعة غير سالبة، والاتجاه `0..360`، والدقة موجبة.
- `recordedAt` تاريخ ISO مع timezone، وليس قديماً أو مستقبلياً أكثر من الحدود المعدة.
- النقطة الأقدم أو المساوية لآخر نقطة مرفوضة.
- حجم رسالة Socket محدود بـ10KB، وعدد التحديثات الافتراضي 12 في الدقيقة.
- هذه الضوابط تمنع القيم غير الصحيحة والإساءة الأساسية، ولا تمنع GPS spoofing بالكامل.

حالات التتبع مؤقتة ولا تعدّل `trips.status`:

- `online`: النشر متصل والتحديث حديث.
- `stale`: لا تحديث منذ 30 ثانية افتراضياً.
- `offline`: لا تحديث منذ 90 ثانية، أو انقطع Socket السائق.
- `stopped`: أوقف السائق النشر عمداً.

رموز الأخطاء: `UNAUTHENTICATED`, `UNAUTHORIZED_TRIP`, `INVALID_LOCATION`, `TRACKING_NOT_STARTED`, `RATE_LIMITED`, `LARAVEL_UNAVAILABLE`.

## الاختبارات

```bash
# من جذر Laravel
php artisan test --filter=RealtimeTrackingAuthorizationTest

# من tracking-service
npm test
```

للاختبار اليدوي، احصل على Sanctum token لسائق من `POST /api/driver/login`. يحتاج الأدمن إلى Sanctum token صادر من Laravel؛ لا يوجد حالياً route عام لتسجيل دخول الأدمن في المشروع. استخدم عميل Socket.IO صغيراً وفق الأمثلة أعلاه، واشترك كأدمن قبل إرسال نقطة السائق.

## حدود النسخة الحالية والانتقال إلى Redis

- لا توجد Checkpoints أو متابعة عملاء أو سجل دائم أو خريطة مضمّنة ضمن هذه الخدمة.
- تضيع المواقع عند إعادة تشغيل Node.js.
- الذاكرة المحلية مناسبة للتطوير ونسخة خدمة واحدة فقط.
- للتوسع الأفقي، نفّذ نفس واجهة `setLatest/getLatest/deleteLatest` باستخدام Redis، وأضف Socket.IO Redis Adapter كي تشترك النسخ في الغرف والبث. لا يحتاج Socket handlers إلى تغيير جوهري.
- استمرار GPS في الخلفية مسؤولية تطبيق الهاتف ونظام التشغيل وأذوناته، وليس Node.js.

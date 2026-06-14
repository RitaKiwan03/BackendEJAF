<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * ✅ حماية من نوع CSRF لـ API يعتمد على Bearer Tokens
 *
 * بما أن التوكنات تُحفظ في localStorage (ليست Cookies)، فالمتصفح لا
 * يرسلها تلقائياً مع طلبات cross-site — لذلك CSRF التقليدي بصيغة
 * csrf_token غير مناسب هنا.
 *
 * الحماية الفعلية: التحقق من Origin/Referer لكل طلب يُغيّر البيانات
 * (POST/PUT/PATCH/DELETE) والتأكد أنه قادم من دومين الفرونت إند
 * المسموح به في cors.php فقط. أي طلب من موقع خبيث آخر سيُرفض بـ 403
 * حتى إذا كان حاملاً توكناً صالحاً (مثلاً عبر سكربت مزروع).
 */
class VerifyOrigin
{
    public function handle(Request $request, Closure $next): Response
    {
        $stateChanging = in_array($request->method(), ['POST', 'PUT', 'PATCH', 'DELETE']);

        if ($stateChanging) {
            $origin = $request->headers->get('Origin') ?? $request->headers->get('Referer');

            // إذا أرسل المتصفح Origin/Referer، يجب أن يطابق دومين مسموح
            if ($origin) {
                $scheme = parse_url($origin, PHP_URL_SCHEME);
                $host   = parse_url($origin, PHP_URL_HOST);
                $port   = parse_url($origin, PHP_URL_PORT);

                $originBase = $scheme . '://' . $host . ($port ? ':' . $port : '');

                $allowed = config('cors.allowed_origins', []);

                $isAllowed = false;
                foreach ($allowed as $allowedOrigin) {
                    if (rtrim($allowedOrigin, '/') === rtrim($originBase, '/')) {
                        $isAllowed = true;
                        break;
                    }
                }

                if (!$isAllowed) {
                    return response()->json([
                        'message' => 'طلب غير مصرح به من هذا المصدر'
                    ], 403);
                }
            }
            // ✅ لا Origin/Referer (مثل أدوات API الموثوقة) → تبقى محمية بـ auth:sanctum
        }

        return $next($request);
    }
}

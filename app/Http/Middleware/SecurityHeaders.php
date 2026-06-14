<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * ✅ يضيف Security Headers لكل الردود
 * يحمي من: Clickjacking, MIME sniffing, XSS, تسريب الـ Referrer
 */
class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // منع تضمين الموقع داخل iframe (Clickjacking)
        $response->headers->set('X-Frame-Options', 'DENY');

        // منع المتصفح من تخمين نوع المحتوى (MIME sniffing)
        $response->headers->set('X-Content-Type-Options', 'nosniff');

        // حماية XSS إضافية للمتصفحات القديمة
        $response->headers->set('X-XSS-Protection', '1; mode=block');

        // تقليل تسريب الـ URL عبر Referrer
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');

        // منع الوصول لميزات الجهاز (كاميرا/مايك/موقع) من المتصفح
        $response->headers->set(
            'Permissions-Policy',
            'geolocation=(), microphone=(), camera=()'
        );

        // ✅ HSTS — يفرض HTTPS لمدة سنة (فقط إذا كان الطلب عبر HTTPS بالفعل)
        if ($request->isSecure()) {
            $response->headers->set(
                'Strict-Transport-Security',
                'max-age=31536000; includeSubDomains'
            );
        }

        return $response;
    }
}

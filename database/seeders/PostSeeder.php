<?php

namespace Database\Seeders;

use App\Models\Post;
use Illuminate\Database\Seeder;

class PostSeeder extends Seeder
{
    public function run(): void
    {
        $posts = [
            [
                'title_en'        => 'The Power of Surveillance: Effective CCTV Solutions for Iraq',
                'title_ar'        => 'قوة المراقبة: حلول CCTV فعالة في العراق',
                'excerpt_en'      => 'How surveillance systems can strengthen public and business safety in a demanding environment.',
                'excerpt_ar'      => 'كيف تعزز أنظمة المراقبة سلامة الأفراد والأعمال في بيئة مليئة بالتحديات.',
                'content_en'      => 'Modern security programs combine cameras, network design, storage, and response workflows so teams can react quickly and reduce blind spots.',
                'content_ar'      => 'تجمع البرامج الأمنية الحديثة بين الكاميرات وتصميم الشبكات والتخزين ومسارات الاستجابة.',
                'slug'            => 'surveillance-cctv-solutions-iraq',
                'image'           => '/mock/blog-surveillance.svg',
                'tags'            => ['Security', 'CCTV', 'Infrastructure'],
                'created_at_display' => '2023-06-14',
            ],
            [
                'title_en'        => 'Navigating the Digital Landscape in Iraq: Top 5 IT Solutions',
                'title_ar'        => 'التنقل في المشهد الرقمي بالعراق: أفضل 5 حلول تقنية',
                'excerpt_en'      => 'A practical look at the technology foundations that help modern businesses move forward.',
                'excerpt_ar'      => 'نظرة عملية على الأسس التقنية التي تساعد الشركات الحديثة على التقدم.',
                'content_en'      => 'Reliable infrastructure starts with clear priorities: availability, security, cloud readiness.',
                'content_ar'      => 'تبدأ البنية التحتية الموثوقة بأولويات واضحة: التوفر والأمان والجاهزية السحابية.',
                'slug'            => 'digital-landscape-it-solutions',
                'image'           => '/mock/blog-digital-landscape.svg',
                'tags'            => ['Cloud', 'IT', 'Strategy'],
                'created_at_display' => '2023-06-14',
            ],
            [
                'title_en'        => 'Elevating Your Business: The Advantages of Being a Gold Partner in Iraq',
                'title_ar'        => 'رفع قيمة الأعمال: مزايا أن تكون شريكاً ذهبياً في العراق',
                'excerpt_en'      => 'Why strong partnerships can increase credibility, reach, and growth opportunities.',
                'excerpt_ar'      => 'لماذا يمكن للشراكات القوية أن تزيد الموثوقية والانتشار وفرص النمو.',
                'content_en'      => 'A strong partner network shortens delivery cycles and improves support quality.',
                'content_ar'      => 'تساعد الشبكة القوية من الشركاء على تقصير دورة التنفيذ وتحسين جودة الدعم.',
                'slug'            => 'gold-partner-value',
                'image'           => '/mock/blog-gold-partner.svg',
                'tags'            => ['Partnerships', 'Growth', 'Business'],
                'created_at_display' => '2023-05-31',
            ],
            [
                'title_en'        => 'Enterprise Modernization Starts With the Network',
                'title_ar'        => 'تبدأ تحديثات المؤسسات من الشبكة',
                'excerpt_en'      => 'A reliable network is the base layer for cloud adoption, security, and application performance.',
                'excerpt_ar'      => 'الشبكة الموثوقة هي الطبقة الأساسية لاعتماد السحابة والأمان وأداء التطبيقات.',
                'content_en'      => 'When the network is designed well, every other system becomes easier to support and scale.',
                'content_ar'      => 'عندما تُصمم الشبكة بشكل صحيح تصبح كل الأنظمة الأخرى أسهل في الدعم والتوسع.',
                'slug'            => 'enterprise-modernization',
                'image'           => '/mock/blog-enterprise-modernization.svg',
                'tags'            => ['Networking', 'Enterprise', 'Performance'],
                'created_at_display' => '2023-05-12',
            ],
        ];

        foreach ($posts as $post) {
            Post::firstOrCreate(
                ['slug' => $post['slug']], // تحقق بالـ slug
                $post
            );
        }
    }
}

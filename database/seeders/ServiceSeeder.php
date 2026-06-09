<?php

namespace Database\Seeders;

use App\Models\Service;
use Illuminate\Database\Seeder;

class ServiceSeeder extends Seeder
{
    public function run(): void
    {
        $services = [
            [
                'title_en'        => 'Data Centers',
                'title_ar'        => 'مراكز البيانات',
                'description_en'  => 'Planning, migration, and implementation for resilient environments built to scale.',
                'description_ar'  => 'التخطيط والترحيل والتنفيذ لبيئات مرنة جاهزة للنمو على نطاق واسع.',
                'icon'            => 'server',
                'gif'             => '/gifs/data-center-solutions_3.gif',
                'order'           => 1,
            ],
            [
                'title_en'        => 'Cloud Computing',
                'title_ar'        => 'الحوسبة السحابية',
                'description_en'  => 'Flexible cloud environments that improve access, resilience, and operational speed.',
                'description_ar'  => 'بيئات سحابية مرنة تعزز الوصول والمرونة وسرعة العمل.',
                'icon'            => 'cloud',
                'gif'             => '/gifs/service-2.gif',
                'order'           => 2,
            ],
            [
                'title_en'        => 'Cyber Security',
                'title_ar'        => 'الأمن السيبراني',
                'description_en'  => 'Enterprise protection strategies designed to reduce risk and strengthen trust.',
                'description_ar'  => 'استراتيجيات حماية مؤسسية لتقليل المخاطر وتعزيز الثقة.',
                'icon'            => 'shield',
                'gif'             => '/gifs/service-4.gif',
                'order'           => 3,
            ],
            [
                'title_en'        => 'Networking Solutions',
                'title_ar'        => 'حلول الشبكات',
                'description_en'  => 'Reliable network design for offices, campuses, and data-driven operations.',
                'description_ar'  => 'تصميم شبكات موثوق للمكاتب والحرم المؤسسي والعمليات المعتمدة على البيانات.',
                'icon'            => 'network',
                'gif'             => '/gifs/service-6.gif',
                'order'           => 4,
            ],
            [
                'title_en'        => 'Intelligent Security',
                'title_ar'        => 'الأمن الذكي',
                'description_en'  => 'Integrated access and surveillance systems that support safer facilities.',
                'description_ar'  => 'أنظمة دخول ومراقبة متكاملة تدعم بيئات أكثر أماناً.',
                'icon'            => 'camera',
                'gif'             => '/gifs/sevice-1.gif',
                'order'           => 5,
            ],
            [
                'title_en'        => 'IT Management',
                'title_ar'        => 'إدارة تقنية المعلومات',
                'description_en'  => 'Managed oversight for systems, updates, and the day-to-day technical workload.',
                'description_ar'  => 'إدارة وتشغيل الأنظمة والتحديثات والأعباء التقنية اليومية.',
                'icon'            => 'workflow',
                'gif'             => '/gifs/service-7.gif',
                'order'           => 6,
            ],
        ];

        foreach ($services as $service) {
            Service::firstOrCreate(
                ['title_en' => $service['title_en']], // تحقق بالعنوان
                $service
            );
        }
    }
}

<?php

namespace Database\Seeders;

use App\Models\Country;
use App\Models\Currency;
use App\Models\Governorate;
use App\Models\Setting;
use App\Models\Unit;
use Illuminate\Database\Seeder;

final class MasterDataSeeder extends Seeder
{
    public function run(): void
    {
        $yemen = Country::updateOrCreate(
            ['code' => 'YE'],
            [
                'name_ar' => 'اليمن',
                'name_en' => 'Yemen',
                'calling_code' => '+967',
                'is_active' => true,
            ]
        );

        Currency::query()->update(['is_default' => false]);
        $yer = Currency::updateOrCreate(['code' => 'YER'], ['name_ar' => 'ريال يمني', 'name_en' => 'Yemeni Rial', 'symbol' => 'ر.ي', 'decimal_places' => 0, 'is_default' => true, 'is_active' => true]);
        Currency::updateOrCreate(['code' => 'SAR'], ['name_ar' => 'ريال سعودي', 'name_en' => 'Saudi Riyal', 'symbol' => 'ر.س', 'decimal_places' => 2, 'is_default' => false, 'is_active' => true]);
        Currency::updateOrCreate(['code' => 'USD'], ['name_ar' => 'دولار أمريكي', 'name_en' => 'US Dollar', 'symbol' => '$', 'decimal_places' => 2, 'is_default' => false, 'is_active' => true]);
        Setting::updateOrCreate(['key' => 'default_currency_id'], ['value' => (string) $yer->id, 'type' => 'integer']);
        Unit::updateOrCreate(['code' => 'piece'], ['name_ar' => 'قطعة', 'name_en' => 'Piece', 'decimal_places' => 0, 'is_active' => true]);
        Unit::updateOrCreate(['code' => 'ml'], ['name_ar' => 'ملليلتر', 'name_en' => 'Millilitre', 'decimal_places' => 2, 'is_active' => true]);
        foreach (['أبين', 'عدن', 'أمانة العاصمة', 'البيضاء', 'الحديدة', 'الجوف', 'المهرة', 'المحويت', 'حجة', 'حضرموت', 'ذمار', 'ريمة', 'سقطرى', 'شبوة', 'صعدة', 'صنعاء', 'تعز', 'عمران', 'لحج', 'مأرب', 'الضالع'] as $index => $name) {
            $isDelivery = in_array($name, ['أمانة العاصمة', 'صنعاء'], true);
            $gov = Governorate::firstOrCreate(
                ['name_ar' => $name],
                [
                    'country_id' => $yemen->id,
                    'slug' => 'governorate-'.($index + 1),
                    'is_active' => true,
                    'is_delivery_active' => $isDelivery,
                ]
            );
            $gov->update([
                'country_id' => $yemen->id,
                'is_delivery_active' => $isDelivery,
            ]);
            Governorate::query()->where('name_ar', $name)->whereNull('country_id')->update(['country_id' => $yemen->id]);
        }
        Setting::upsert([
            ['key' => 'application_name', 'value' => "AL-Wafa'a Store", 'type' => 'string'],
            ['key' => 'default_locale', 'value' => 'ar', 'type' => 'string'],
        ], ['key'], ['value', 'type']);
    }
}

<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Catalog;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;

final class VisualSearchCustomerProductsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'image' => [
                'required',
                'file',
                'mimes:jpeg,jpg,png,webp',
                'max:5120', // 5MB limit
                'dimensions:min_width=50,min_height=50,max_width=4000,max_height=4000',
            ],
            'limit' => [
                'sometimes',
                'integer',
                'min:1',
                'max:50',
            ],
            'min_similarity' => [
                'sometimes',
                'numeric',
                'min:0.1',
                'max:1.0',
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'image.required' => 'يرجى تزويد صورة للبحث',
            'image.file' => 'الملف المرفق يجب أن يكون ملفاً صالحاً',
            'image.mimes' => 'صيغة الصورة غير مدعومة. الصيغ المدعومة: JPEG, PNG, WEBP',
            'image.max' => 'حجم الصورة يجب ألا يتجاوز 5 ميجابايت',
            'image.dimensions' => 'أبعاد الصورة غير صالحة (الحد الأدنى 50×50 والحد الأقصى 4000×4000 بكسل)',
            'limit.integer' => 'معامل عدد النتائج يجب أن يكون عدداً صحيحاً',
            'limit.min' => 'الحد الأدنى لعدد النتائج هو 1',
            'limit.max' => 'الحد الأقصى لعدد النتائج هو 50',
            'min_similarity.numeric' => 'معامل نسبة التطابق يجب أن يكون قيمة رقمية',
            'min_similarity.min' => 'أدنى نسبة تطابق هي 0.1',
            'min_similarity.max' => 'أعلى نسبة تطابق هي 1.0',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v) {
            $file = $this->file('image');
            if ($file === null || ! $file->isValid()) {
                return;
            }

            // Verify the file is actually a decodable image and not a corrupt or polyglot file
            $filePath = $file->getRealPath();
            if (! $filePath || ! file_exists($filePath)) {
                $v->errors()->add('image', 'تعذر قراءة ملف الصورة المرفق');

                return;
            }

            $imageInfo = @getimagesize($filePath);
            if ($imageInfo === false) {
                $v->errors()->add('image', 'محتوى الصورة تالف أو غير قابل للمعالجة');
            }
        });
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(
            response()->json([
                'success' => false,
                'message' => 'بيانات البحث المرئي غير صالحة',
                'code' => 'VISUAL_SEARCH_INVALID_IMAGE',
                'errors' => $validator->errors(),
            ], JsonResponse::HTTP_UNPROCESSABLE_ENTITY)
        );
    }
}

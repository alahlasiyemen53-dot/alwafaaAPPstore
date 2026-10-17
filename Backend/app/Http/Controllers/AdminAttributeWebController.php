<?php

namespace App\Http\Controllers;

use App\Actions\ActivateAttributeAction;
use App\Actions\ActivateAttributeValueAction;
use App\Actions\CreateAttributeAction;
use App\Actions\CreateAttributeValueAction;
use App\Actions\DeactivateAttributeAction;
use App\Actions\DeactivateAttributeValueAction;
use App\Actions\UpdateAttributeAction;
use App\Actions\UpdateAttributeValueAction;
use App\Data\AttributeData;
use App\Data\AttributeValueData;
use App\Http\Requests\Admin\StoreAttributeRequest;
use App\Http\Requests\Admin\StoreAttributeValueRequest;
use App\Http\Requests\Admin\UpdateAttributeRequest;
use App\Http\Requests\Admin\UpdateAttributeValueRequest;
use App\Models\Attribute;
use App\Models\AttributeValue;
use App\Queries\AdminAttributeQuery;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

final class AdminAttributeWebController extends Controller
{
    public function index(Request $request, AdminAttributeQuery $query): View
    {
        return view('admin.attributes.index', [
            'attributes' => $query->paginate(
                20,
                $request->string('search')->toString(),
                $request->string('status')->toString(),
            ),
        ]);
    }

    public function create(): View
    {
        return view('admin.attributes.form', ['attribute' => null]);
    }

    public function store(StoreAttributeRequest $request, CreateAttributeAction $action): RedirectResponse
    {
        $attribute = $action->execute(AttributeData::fromValidated($request->validated()));

        return redirect()->route('admin.attributes.values.index', $attribute)
            ->with('success', 'تم إنشاء الخاصية بنجاح. يمكنك الآن إضافة القيم التابعة لها.');
    }

    public function edit(Attribute $attribute): View
    {
        return view('admin.attributes.form', ['attribute' => $attribute]);
    }

    public function update(UpdateAttributeRequest $request, Attribute $attribute, UpdateAttributeAction $action): RedirectResponse
    {
        $action->execute($attribute, AttributeData::fromValidated($request->validated()));

        return redirect()->route('admin.attributes.index')->with('success', 'تم تحديث الخاصية بنجاح.');
    }

    public function activate(Attribute $attribute, ActivateAttributeAction $action): RedirectResponse
    {
        try {
            $action->execute($attribute);
        } catch (ConflictHttpException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'تم تفعيل الخاصية.');
    }

    public function deactivate(Attribute $attribute, DeactivateAttributeAction $action): RedirectResponse
    {
        try {
            $action->execute($attribute);
        } catch (ConflictHttpException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'تم تعطيل الخاصية.');
    }

    public function values(Attribute $attribute): View
    {
        $values = $attribute->values()->orderBy('sort_order')->orderBy('id')->get();

        return view('admin.attributes.values.index', [
            'attribute' => $attribute,
            'values' => $values,
        ]);
    }

    public function storeValue(StoreAttributeValueRequest $request, Attribute $attribute, CreateAttributeValueAction $action): RedirectResponse
    {
        $action->execute(AttributeValueData::fromValidated($attribute->id, $request->validated()));

        return back()->with('success', 'تمت إضافة القيمة بنجاح.');
    }

    public function updateValue(
        UpdateAttributeValueRequest $request,
        Attribute $attribute,
        AttributeValue $value,
        UpdateAttributeValueAction $action,
    ): RedirectResponse {
        if ($value->attribute_id !== $attribute->id) {
            abort(404);
        }

        $action->execute($value, AttributeValueData::fromValidated($attribute->id, $request->validated()));

        return back()->with('success', 'تم تحديث القيمة بنجاح.');
    }

    public function activateValue(
        Attribute $attribute,
        AttributeValue $value,
        ActivateAttributeValueAction $action,
    ): RedirectResponse {
        if ($value->attribute_id !== $attribute->id) {
            abort(404);
        }

        try {
            $action->execute($value);
        } catch (ConflictHttpException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'تم تفعيل القيمة.');
    }

    public function deactivateValue(
        Attribute $attribute,
        AttributeValue $value,
        DeactivateAttributeValueAction $action,
    ): RedirectResponse {
        if ($value->attribute_id !== $attribute->id) {
            abort(404);
        }

        try {
            $action->execute($value);
        } catch (ConflictHttpException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'تم تعطيل القيمة.');
    }
}

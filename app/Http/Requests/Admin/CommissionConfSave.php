<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class CommissionConfSave extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return [
            'user_id' => 'required',
            'price' => 'required',
            'rate' => 'required',
            'sore' => 'required',
            'status' => 'required'
        ];
    }

    public function messages()
    {
        return [
            'user_id.required' => '用户id不能为空',
            'price.required' => '价格阶段不能为空',
            'rate.url' => '比例不能为空',
            'sore.array' => '排序不能为空',
            'status.array' => '状态不能为空',
        ];
    }
}

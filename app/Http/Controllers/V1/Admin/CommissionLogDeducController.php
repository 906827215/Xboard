<?php

namespace App\Http\Controllers\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\CommissionLogDeduc;
use Illuminate\Http\Request;

class CommissionLogDeducController extends Controller
{
    public function fetch(Request $request)
    {
        $request->validate([
            'user_id' => 'required|integer'
        ]);
        $current = $request->input('current') ? $request->input('current') : 1;
        $pageSize = $request->input('pageSize') >= 10 ? $request->input('pageSize') : 10;
        $sortType = in_array($request->input('sort_type'), ['ASC', 'DESC']) ? $request->input('sort_type') : 'DESC';
        $sort = $request->input('sort') ? $request->input('sort') : 'created_at';

        $builder = CommissionLogDeduc::orderBy($sort, $sortType)->where('user_id', $request->input('user_id'));

        $total = $builder->count();
        $commissionLogDeduc = $builder->forPage($current, $pageSize)
            ->get();
        return response([
            'data' => $commissionLogDeduc,
            'total' => $total
        ]);
    }

    public function total(Request $request)
    {
        $request->validate([
            'user_id' => 'required|integer'
        ]);
        $total = CommissionLogDeduc::where('user_id', $request->input('user_id'))
            ->sum('get_amount');
        if (!$total) {
            return $this->fail([400202,'佣金扣除记录不存在']);
        }
        return $this->success($total);
    }

    public function show(Request $request)
    {
        $request->validate([
            'id' => 'required|numeric'
        ],[
            'id.required' => 'ID不能为空',
            'id.numeric' => 'ID必须为数字'
        ]);
        $commissionLogDeduc = CommissionLogDeduc::find($request->input('id'));
        if (!$commissionLogDeduc) {
            return $this->fail([400202,'佣金扣除记录不存在']);
        }
        return $this->success($commissionLogDeduc);
    }



}

<?php

namespace App\Http\Controllers\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\CommissionConfSave;
use App\Models\CommissionConf;
use Illuminate\Http\Request;

class CommissionConfController extends Controller
{
    public function fetch(Request $request)
    {
        $request->validate([
            'user_id' => 'required|integer'
        ]);
        $current = $request->input('current') ? $request->input('current') : 1;
        $pageSize = $request->input('pageSize') >= 10 ? $request->input('pageSize') : 10;
        $sortType = in_array($request->input('sort_type'), ['ASC', 'DESC']) ? $request->input('sort_type') : 'ASC';
        $sort = $request->input('sort') ? $request->input('sort') : 'sore';

        $builder = CommissionConf::orderBy($sort, $sortType)->where('user_id', $request->input('user_id'));

        $total = $builder->count();
        $commissionConf = $builder->forPage($current, $pageSize)
            ->get();
        return response([
            'data' => $commissionConf,
            'total' => $total
        ]);
    }

    public function save(CommissionConfSave $request)
    {
        $data = $request->only([
            'user_id',
            'price',
            'rate',
            'sore',
            'status'
        ]);
        if (!$request->input('id')) {
            if (!CommissionConf::create($data)) {
                return $this->fail([500 ,'保存失败']);
            }
        } else {
            try {
                CommissionConf::find($request->input('id'))->update($data);
            } catch (\Exception $e) {
                return $this->fail([500 ,'保存失败']);
            }
        }
        return $this->success(true);
    }

    public function show(Request $request)
    {
        $request->validate([
            'id' => 'required|numeric'
        ],[
            'id.required' => 'ID不能为空',
            'id.numeric' => 'ID必须为数字'
        ]);
        $commissionCon = CommissionConf::find($request->input('id'));
        if (!$commissionCon) {
            return $this->fail([400202,'佣金配置不存在']);
        }
        return $this->success($commissionCon);
    }

    public function drop(Request $request)
    {
        $request->validate([
            'id' => 'required|numeric'
        ],[
            'id.required' => 'ID不能为空',
            'id.numeric' => 'ID必须为数字'
        ]);

        if ($request->input('id')) {
            $commissionConf = CommissionConf::find($request->input('id'));
            if (!$commissionConf) {
                return $this->fail([400202 ,'该配置不存在']);
            }
        }
        return $this->success($commissionConf->delete());
    }

}

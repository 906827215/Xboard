<?php

namespace App\Http\Controllers\V1\User;

use App\Exceptions\ApiException;
use App\Http\Controllers\Controller;
use App\Http\Resources\ComissionLogResource;
use App\Http\Resources\InviteCodeResource;
use App\Models\CommissionLog;
use App\Models\InviteCode;
use App\Models\Order;
use App\Models\User;
use App\Utils\Helper;
use DateTime;
use Illuminate\Http\Request;

class InviteController extends Controller
{
    public function save(Request $request)
    {
        if (InviteCode::where('user_id', $request->user['id'])->where('status', 0)->count() >= admin_setting('invite_gen_limit', 5)) {
            return $this->fail([400,__('The maximum number of creations has been reached')]);
        }
        $inviteCode = new InviteCode();
        $inviteCode->user_id = $request->user['id'];
        $inviteCode->code = Helper::randomChar(8);
        return $this->success($inviteCode->save());
    }

    public function details(Request $request)
    {
        $current = $request->input('current') ? $request->input('current') : 1;
        $pageSize = $request->input('page_size') >= 10 ? $request->input('page_size') : 10;
        $builder = CommissionLog::where('invite_user_id', $request->user['id'])
            ->where('get_amount', '>', 0)
            ->orderBy('created_at', 'DESC');
        $total = $builder->count();
        $details = $builder->forPage($current, $pageSize)
            ->get();
        return response([
            'data' => ComissionLogResource::collection($details),
            'total' => $total
        ]);
    }

    public function fetch(Request $request)
    {
        $commission_rate = admin_setting('invite_commission', 10);
        $user = User::find($request->user['id'])
                ->load(['codes' => fn($query) => $query->where('status', 0)]);
        if ($user->commission_rate) {
            $commission_rate = $user->commission_rate;
        }
        $uncheck_commission_balance = (int)Order::where('status', 3)
            ->where('commission_status', 0)
            ->where('invite_user_id', $user->id)
            ->sum('commission_balance');
        if (admin_setting('commission_distribution_enable', 0)) {
            $uncheck_commission_balance = $uncheck_commission_balance * (admin_setting('commission_distribution_l1') / 100);
        }

        // 当前时间戳
        $currentTimestamp = time();
        // 当月开始时间戳
        $currentMonthStart = strtotime(date('Y-m-01 00:00:00'));
        // 当月结束时间戳
        $currentMonthEnd = strtotime(date('Y-m-t 23:59:59'));
        // 上月开始时间戳
        $lastMonthStart = strtotime(date('Y-m-01 00:00:00', strtotime('first day of last month')));
        // 上月结束时间戳
        $lastMonthEnd = strtotime(date('Y-m-t 23:59:59', strtotime('last day of last month')));

        # 老写法
//            CommissionLog::where('invite_user_id', $user->id)
//                ->whereBetween('created_at', [$currentMonthStart, $currentMonthEnd])
//                ->whereIn('user_id', User::where('invite_user_id', $user->id)
//                    ->whereBetween('created_at', [$currentMonthStart, $currentMonthEnd])
//                    ->pluck('id'))
//                ->distinct('userid')
//                ->count('userid'),

        $stat = [
            //已注册用户数 / 上月 / 当月
            (int)User::where('invite_user_id', $user->id)->count(),
            (int)User::where('invite_user_id', $user->id)
                ->whereBetween('created_at', [$lastMonthStart, $lastMonthEnd])
                ->count(),
            (int)User::where('invite_user_id', $user->id)
                ->whereBetween('created_at', [$currentMonthStart, $currentMonthEnd])
                ->count(),
            # 充值新人数
            CommissionLog::join('v2_user', 'v2_commission_log.user_id', '=', 'v2_user.id')
                ->where('v2_commission_log.invite_user_id', $user->id)
                ->whereBetween('v2_commission_log.created_at', [$lastMonthStart, $lastMonthEnd])
                ->where('v2_user.invite_user_id', $user->id)
                ->whereBetween('v2_user.created_at', [$lastMonthStart, $lastMonthEnd])
                ->distinct('v2_commission_log.user_id')
                ->count('v2_commission_log.user_id'),

            CommissionLog::join('v2_user', 'v2_commission_log.user_id', '=', 'v2_user.id')
                ->where('v2_commission_log.invite_user_id', $user->id)
                ->whereBetween('v2_commission_log.created_at', [$currentMonthStart, $currentMonthEnd])
                ->where('v2_user.invite_user_id', $user->id)
                ->whereBetween('v2_user.created_at', [$currentMonthStart, $currentMonthEnd])
                ->distinct('v2_commission_log.user_id')
                ->count('v2_commission_log.user_id'),

            //佣金
            (int)CommissionLog::where('invite_user_id', $user->id)
                ->sum('get_amount'),
            (int)CommissionLog::where('invite_user_id', $user->id)
                ->whereBetween('created_at', [$lastMonthStart, $lastMonthEnd])
                ->sum('get_amount'),
            (int)CommissionLog::where('invite_user_id', $user->id)
                ->whereBetween('created_at', [$currentMonthStart, $currentMonthEnd])
                ->sum('get_amount'),

            //佣金比例
            (int)$commission_rate,
            //确认中的佣金
            $uncheck_commission_balance,
            //可用佣金
            (int)$user->commission_balance
        ];
        $data = [
            'codes' => InviteCodeResource::collection($user->codes),
            'stat' => $stat
        ];
        return $this->success($data);
    }

    public function check(Request $request)
    {
        $user = User::find($request->user['id'])
            ->load(['codes' => fn($query) => $query->where('status', 0)]);
        $startTimestamp = $request->input('start');
        $endOfTimestamp = $request->input('end');

        # 首充
        $pay_num = CommissionLog::join('v2_user', 'v2_commission_log.user_id', '=', 'v2_user.id')
            ->where('v2_commission_log.invite_user_id', $user->id)
            ->whereBetween('v2_commission_log.created_at', [$startTimestamp, $endOfTimestamp])
            ->where('v2_user.invite_user_id', $user->id)
            ->whereBetween('v2_user.created_at', [$startTimestamp, $endOfTimestamp])
            ->distinct('v2_commission_log.user_id')
            ->count('v2_commission_log.user_id');
        # 新注册
        $reg_num = User::where('invite_user_id', $user->id)
            ->whereBetween('created_at', [$startTimestamp, $endOfTimestamp])
            ->count('id');
        if (!$pay_num){
            $rate = 0;
        }else{
            $rate = number_format($pay_num / $reg_num * 100, 2);
        }

        $amount = (int)CommissionLog::where('invite_user_id', $user->id)
            ->whereBetween('created_at', [$startTimestamp, $endOfTimestamp])
            ->sum('get_amount');
        $stat = [
            $pay_num,
            $reg_num,
            $amount,
            $rate,
        ];
        $data = [
            'stat' => $stat
        ];
        return $this->success($data);
    }
}

<?php

namespace App\Http\Controllers\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\CommissionLog;
use App\Models\Order;
use App\Models\ServerHysteria;
use App\Models\ServerVless;
use App\Models\ServerShadowsocks;
use App\Models\ServerTrojan;
use App\Models\ServerVmess;
use App\Models\Stat;
use App\Models\StatServer;
use App\Models\StatUser;
use App\Models\Ticket;
use App\Models\User;
use App\Services\StatisticalService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class StatController extends Controller
{
    public function getOverride(Request $request)
    {
        # 需要添加今日和昨日的支付通道总金额
        $yesterday = strtotime('yesterday 00:00:00');
        $today_start = strtotime(date('Y-m-d'));

        $now = time();
        $now_year_time = $now + 15552000;

        # 今日支付通统计
        $today_list = DB::table('v2_payment')
            ->leftJoin('v2_order', function ($join) use ($today_start, $now) {
                $join->on('v2_payment.id', '=', 'v2_order.payment_id')
                    ->whereBetween('v2_order.created_at', [$today_start, $now])
                    ->whereNotIn('v2_order.status', [0, 2]);
            })
            ->select('v2_payment.id as channel_id', 'v2_payment.name as channel_name')
            ->selectRaw('COALESCE(SUM(v2_order.total_amount), 0)  as total_amount')
            ->groupBy('v2_payment.id', 'v2_payment.name')
            ->get();

        # 昨日支付通道统计
        $yesterday_list = DB::table('v2_payment')
            ->leftJoin('v2_order', function ($join) use ($yesterday, $today_start) {
                $join->on('v2_payment.id', '=', 'v2_order.payment_id')
                    ->whereBetween('v2_order.created_at', [$yesterday, $today_start])
                    ->whereNotIn('v2_order.status', [0, 2]);
            })
            ->select('v2_payment.id as channel_id', 'v2_payment.name as channel_name')
            ->selectRaw('COALESCE(SUM(v2_order.total_amount), 0)  as total_amount')
            ->groupBy('v2_payment.id', 'v2_payment.name')
            ->get();

        return [
            'data' => [
                # 流水
                'day_income' => Order::where('created_at', '>=', strtotime(date('Y-m-d')))
                    ->where('created_at', '<', $now)
                    ->whereNotIn('status', [0, 2])
                    ->sum('total_amount'),
                'last_day_income' => Order::where('created_at', '>=', strtotime('yesterday 00:00:00'))
                    ->where('created_at', '<', strtotime('today 00:00:00'))
                    ->whereNotIn('status', [0, 2])
                    ->sum('total_amount'),
                'month_income' => Order::where('created_at', '>=', strtotime(date('Y-m-1')))
                    ->where('created_at', '<', $now)
                    ->whereNotIn('status', [0, 2])
                    ->sum('total_amount'),
                'last_month_income' => Order::where('created_at', '>=', strtotime('-1 month', strtotime(date('Y-m-1'))))
                    ->where('created_at', '<', strtotime(date('Y-m-1')))
                    ->whereNotIn('status', [0, 2])
                    ->sum('total_amount'),
                'total_income' => Order::whereNotIn('status', [0, 2])
                    ->sum('total_amount'),

                # 佣金
                'commission_month_payout' => CommissionLog::where('created_at', '>=', strtotime(date('Y-m-1')))
                    ->where('created_at', '<', $now)
                    ->sum('get_amount'),
                'commission_last_month_payout' => CommissionLog::where('created_at', '>=', strtotime('-1 month', strtotime(date('Y-m-1'))))
                    ->where('created_at', '<', strtotime(date('Y-m-1')))
                    ->sum('get_amount'),
                # 注册
                'month_register_total' => User::where('created_at', '>=', strtotime(date('Y-m-1')))
                    ->where('created_at', '<', $now)
                    ->count(),
                'last_register_total' => User::where('created_at', '>=', strtotime('-1 month', strtotime(date('Y-m-1'))))
                    ->where('created_at', '<', strtotime(date('Y-m-1')))
                    ->count(),

                # 工单和待确认佣金
                'ticket_pending_total' => Ticket::where('status', 0)
                    ->count(),
                'commission_pending_total' => Order::where('commission_status', 0)
                    ->where('invite_user_id', '!=', NULL)
                    ->whereNotIn('status', [0, 2])
                    ->where('commission_balance', '>', 0)
                    ->count(),
                'today_list' => $today_list,
                'yesterday_list' => $yesterday_list,
                'pay_num' => User::whereBetween('created_at', [strtotime(date('Y-m-d')), $now])
                    ->whereNotNull('plan_id')
                    ->count('id'),
                'reg_num' => User::whereBetween('created_at', [strtotime(date('Y-m-d')), $now])->count('id'),
                'last_pay_num' => User::where('created_at', '>=', strtotime('yesterday 00:00:00'))
                    ->where('created_at', '<', strtotime('today 00:00:00'))
                    ->whereNotNull('plan_id')
                    ->count('id'),
                'last_reg_num' => User::where('created_at', '>=', strtotime('yesterday 00:00:00'))
                    ->where('created_at', '<', strtotime('today 00:00:00'))
                    ->count('id'),
                'plan_nums' => User::whereNotNull('plan_id')->where('expired_at', '>', $now)->count('id'),
                'plan1_nums' => User::where('plan_id','=', 1)->where('expired_at', '>=', $now)->count('id'),
                'plan1_year_nums' => User::where('plan_id','=', 1)->where('expired_at', '>=', $now_year_time)->count('id'),
                'plan2_nums' => User::where('plan_id','=', 2)->where('expired_at', '>', $now)->count('id'),
                'plan2_year_nums' => User::where('plan_id','=', 2)->where('expired_at', '>=', $now_year_time)->count('id'),
                'plan3_nums' => User::where('plan_id','=', 3)->where('expired_at', '>', $now)->count('id'),
                'plan3_year_nums' => User::where('plan_id','=', 3)->where('expired_at', '>=', $now_year_time)->count('id'),
                'plan4_nums' => User::where('plan_id','=', 4)->where('expired_at', '>', $now)->count('id'),
                'plan4_year_nums' => User::where('plan_id','=', 5)->where('expired_at', '>=', $now_year_time)->count('id'),
                'plan5_nums' => User::where('plan_id','=', 5)->where('expired_at', '>', $now)->count('id'),
                'plan5_year_nums' => User::where('plan_id','=', 5)->where('expired_at', '>=', $now_year_time)->count('id'),
                'plan6_nums' => User::where('plan_id','=', 7)->where('expired_at', '>', $now)->count('id'),
                'plan6_year_nums' => User::where('plan_id','=', 7)->where('expired_at', '>=', $now_year_time)->count('id'),

            ]
        ];
    }

    public function getOrder(Request $request)
    {
        $statistics = Stat::where('record_type', 'd')
            ->limit(31)
            ->orderBy('record_at', 'DESC')
            ->get()
            ->toArray();
        $result = [];
        foreach ($statistics as $statistic) {
            $date = date('m-d', $statistic['record_at']);
            $result[] = [
                'type' => '收款金额',
                'date' => $date,
                'value' => $statistic['paid_total'] / 100
            ];
            $result[] = [
                'type' => '收款笔数',
                'date' => $date,
                'value' => $statistic['paid_count']
            ];
            $result[] = [
                'type' => '佣金金额(已发放)',
                'date' => $date,
                'value' => $statistic['commission_total'] / 100
            ];
            $result[] = [
                'type' => '佣金笔数(已发放)',
                'date' => $date,
                'value' => $statistic['commission_count']
            ];
        }
        $result = array_reverse($result);
        return [
            'data' => $result
        ];
    }

    // 获取当日实时流量排行
    public function getServerLastRank()
    {
        $servers = [
            'shadowsocks' => ServerShadowsocks::with(['parent'])->get()->toArray(),
            'v2ray' => ServerVmess::with(['parent'])->get()->toArray(),
            'trojan' => ServerTrojan::with(['parent'])->get()->toArray(),
            'vmess' => ServerVmess::with(['parent'])->get()->toArray(),
            'hysteria' => ServerHysteria::with(['parent'])->get()->toArray(),
            'vless' => ServerVless::with(['parent'])->get()->toArray(),
        ];

        $recordAt = strtotime(date('Y-m-d'));
        $statService = new StatisticalService();
        $statService->setStartAt($recordAt);
        $stats = $statService->getStatServer();
        $statistics = collect($stats)->map(function ($item){
            $item['total'] = $item['u'] + $item['d'];
            return $item;
        })->sortByDesc('total')->values()->all();
        foreach ($statistics as $k => $v) {
            foreach ($servers[$v['server_type']] as $server) {
                if ($server['id'] === $v['server_id']) {
                    $statistics[$k]['server_name'] = $server['name'];
                    if($server['parent']) $statistics[$k]['server_name'] .= "({$server['parent']['name']})";
                }
            }
            $statistics[$k]['total'] = $statistics[$k]['total'] / 1073741824;
        }
        array_multisort(array_column($statistics, 'total'), SORT_DESC, $statistics);
        return [
            'data' => collect($statistics)->take(15)->all()
        ];
    }
    // 获取昨日节点流量排行
    public function getServerYesterdayRank()
    {
        $servers = [
            'shadowsocks' => ServerShadowsocks::with(['parent'])->get()->toArray(),
            'v2ray' => ServerVmess::with(['parent'])->get()->toArray(),
            'trojan' => ServerTrojan::with(['parent'])->get()->toArray(),
            'vmess' => ServerVmess::with(['parent'])->get()->toArray(),
            'hysteria' => ServerHysteria::with(['parent'])->get()->toArray(),
            'vless' => ServerVless::with(['parent'])->get()->toArray(),
        ];
        $startAt = strtotime('-1 day', strtotime(date('Y-m-d')));
        $endAt = strtotime(date('Y-m-d'));
        $statistics = StatServer::select([
            'server_id',
            'server_type',
            'u',
            'd',
            DB::raw('(u+d) as total')
        ])
            ->where('record_at', '>=', $startAt)
            ->where('record_at', '<', $endAt)
            ->where('record_type', 'd')
            ->limit(15)
            ->orderBy('total', 'DESC')
            ->get()
            ->toArray();
        foreach ($statistics as $k => $v) {
            foreach ($servers[$v['server_type']] as $server) {
                if ($server['id'] === $v['server_id']) {
                    $statistics[$k]['server_name'] = $server['name'];
                    if($server['parent']) $statistics[$k]['server_name'] .= "({$server['parent']['name']})";
                }
            }
            $statistics[$k]['total'] = $statistics[$k]['total'] / 1073741824;
        }
        array_multisort(array_column($statistics, 'total'), SORT_DESC, $statistics);
        return [
            'data' => $statistics
        ];
    }

    public function getStatUser(Request $request)
    {
        $request->validate([
            'user_id' => 'required|integer'
        ]);
        $current = $request->input('current') ? $request->input('current') : 1;
        $pageSize = $request->input('pageSize') >= 10 ? $request->input('pageSize') : 10;
        $builder = StatUser::orderBy('record_at', 'DESC')->where('user_id', $request->input('user_id'));

        $total = $builder->count();
        $records = $builder->forPage($current, $pageSize)
            ->get();

        // 追加当天流量
        $recordAt = strtotime(date('Y-m-d'));
        $statService = new StatisticalService();
        $statService->setStartAt($recordAt);
        $todayTraffics = $statService->getStatUserByUserID($request->input('user_id'));
        if (($current == 1)  && count($todayTraffics) > 0) {
            foreach ($todayTraffics as $todayTraffic){
                $todayTraffic['server_rate'] = number_format($todayTraffic['server_rate'], 2);
                $records->prepend($todayTraffic);
            }
        };

        return [
            'data' => $records,
            'total' => $total + count($todayTraffics),
        ];
    }

}


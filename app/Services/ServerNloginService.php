<?php

namespace App\Services;

use App\Models\ServerHysteria;
use App\Models\ServerLog;
use App\Models\ServerRoute;
use App\Models\ServerShadowsocks;
use App\Models\ServerVless;
use App\Models\User;
use App\Models\ServerVmess;
use App\Models\ServerTrojan;
use App\Utils\CacheKey;
use App\Utils\Helper;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

class ServerNloginService
{
    // 获取可用的 VLESS 服务器列表
    public static function getAvailableVless(): array
    {
        $servers = [];
        $model = ServerVless::orderBy('sort', 'ASC');
        $server = $model->get();
        foreach ($server as $key => $v) {
            if (!$v['show']) continue;
            $serverData = $v->toArray();

            $serverData['type'] = 'vless';
            if (strpos($serverData['port'], '-') !== false) {
                $serverData['port'] = Helper::randomPort($serverData['port']);
            }
            if ($serverData['parent_id']) {
                $serverData['last_check_at'] = Cache::get(CacheKey::get('SERVER_VLESS_LAST_CHECK_AT', $serverData['parent_id']));
            } else {
                $serverData['last_check_at'] = Cache::get(CacheKey::get('SERVER_VLESS_LAST_CHECK_AT', $serverData['id']));
            }
            if (isset($serverData['tls_settings'])) {
                if (isset($serverData['tls_settings']['private_key'])) {
                    unset($serverData['tls_settings']['private_key']);
                }
            }

            $servers[] = $serverData;
        }

        return $servers;
    }

    // 获取可用的 VMESS 服务器列表
    public static function getAvailableVmess(): array
    {
        $servers = [];
        $model = ServerVmess::orderBy('sort', 'ASC');
        $vmess = $model->get();
        foreach ($vmess as $key => $v) {
            if (!$v['show']) continue;
            $vmess[$key]['type'] = 'vmess';
            if (strpos($vmess[$key]['port'], '-') !== false) {
                $vmess[$key]['port'] = Helper::randomPort($vmess[$key]['port']);
            }
            if ($vmess[$key]['parent_id']) {
                $vmess[$key]['last_check_at'] = Cache::get(CacheKey::get('SERVER_VMESS_LAST_CHECK_AT', $vmess[$key]['parent_id']));
            } else {
                $vmess[$key]['last_check_at'] = Cache::get(CacheKey::get('SERVER_VMESS_LAST_CHECK_AT', $vmess[$key]['id']));
            }
            $servers[] = $vmess[$key]->toArray();
        }

        return $servers;
    }

    // 获取可用的 TROJAN 服务器列表
    public static function getAvailableTrojan(): array
    {
        $servers = [];
        $model = ServerTrojan::orderBy('sort', 'ASC');
        $trojan = $model->get();
        foreach ($trojan as $key => $v) {
            if (!$v['show']) continue;
            $trojan[$key]['type'] = 'trojan';
            if (strpos($trojan[$key]['port'], '-') !== false) {
                $trojan[$key]['port'] = Helper::randomPort($trojan[$key]['port']);
            }
            if ($trojan[$key]['parent_id']) {
                $trojan[$key]['last_check_at'] = Cache::get(CacheKey::get('SERVER_TROJAN_LAST_CHECK_AT', $trojan[$key]['parent_id']));
            } else {
                $trojan[$key]['last_check_at'] = Cache::get(CacheKey::get('SERVER_TROJAN_LAST_CHECK_AT', $trojan[$key]['id']));
            }
            $servers[] = $trojan[$key]->toArray();
        }
        return $servers;
    }

    // 获取可用的 HYSTERIA 服务器列表
    public static function getAvailableHysteria()
    {
        $availableServers = [];
        $model = ServerHysteria::orderBy('sort', 'ASC');
        $servers = $model->get()->keyBy('id');
        foreach ($servers as $key => $v) {
            if (!$v['show']) continue;
            $servers[$key]['type'] = 'hysteria';
            $servers[$key]['last_check_at'] = Cache::get(CacheKey::get('SERVER_HYSTERIA_LAST_CHECK_AT', $v['id']));
            if (strpos($v['port'], '-') !== false) {
                $servers[$key]['ports'] = $v['port'];
                $servers[$key]['port'] = Helper::randomPort($v['port']);
            }
            if (isset($servers[$v['parent_id']])) {
                $servers[$key]['last_check_at'] = Cache::get(CacheKey::get('SERVER_HYSTERIA_LAST_CHECK_AT', $v['parent_id']));
                $servers[$key]['created_at'] = $servers[$v['parent_id']]['created_at'];
            }
            $servers[$key]['server_key'] = Helper::getServerKey($servers[$key]['created_at'], 16);
            $availableServers[] = $servers[$key]->toArray();
        }
        return $availableServers;
    }

    // 获取可用的 SHADOWSOCKS 服务器列表
    public static function getAvailableShadowsocks()
    {
        $servers = [];
        $model = ServerShadowsocks::orderBy('sort', 'ASC');
        $shadowsocks = $model->get()->keyBy('id');
        foreach ($shadowsocks as $key => $v) {
            if (!$v['show']) continue;
            $shadowsocks[$key]['type'] = 'shadowsocks';
            $shadowsocks[$key]['last_check_at'] = Cache::get(CacheKey::get('SERVER_SHADOWSOCKS_LAST_CHECK_AT', $v['id']));
            if (strpos($v['port'], '-') !== false) {
                $shadowsocks[$key]['port'] = Helper::randomPort($v['port']);
            }
            if (isset($shadowsocks[$v['parent_id']])) {
                $shadowsocks[$key]['last_check_at'] = Cache::get(CacheKey::get('SERVER_SHADOWSOCKS_LAST_CHECK_AT', $v['parent_id']));
                $shadowsocks[$key]['created_at'] = $shadowsocks[$v['parent_id']]['created_at'];
            }
            // 处理ss2022密码
            $cipherConfiguration = [
                '2022-blake3-aes-128-gcm' => [
                    'serverKeySize' => 16,
                    'userKeySize' => 16,
                ],
                '2022-blake3-aes-256-gcm' => [
                    'serverKeySize' => 32,
                    'userKeySize' => 32,
                ],
                '2022-blake3-chacha20-poly1305' => [
                    'serverKeySize' => 32,
                    'userKeySize' => 32,
                ]
            ];

            if ($v['obfs'] === 'http') {
                $shadowsocks[$key]['obfs'] = 'http';
                $shadowsocks[$key]['obfs-host'] = $v['obfs_settings']['host'];
                $shadowsocks[$key]['obfs-path'] = $v['obfs_settings']['path'];
            }
            $servers[] = $shadowsocks[$key]->toArray();
        }
        return $servers;
    }

    // 获取可用的服务器列表
    public static function getServers()
    {
        $servers = array_merge(
            self::getAvailableShadowsocks(),
            self::getAvailableVmess(),
            self::getAvailableTrojan(),
            self::getAvailableHysteria(),
            self::getAvailableVless()
        );
        $tmp = array_column($servers, 'sort');
        array_multisort($tmp, SORT_ASC, $servers);
        return array_map(function ($server) {
            $server['port'] = (int)$server['port'];
            $server['is_online'] = (time() - 300 > $server['last_check_at']) ? 0 : 1;
            $server['cache_key'] = "{$server['type']}-{$server['id']}-{$server['updated_at']}-{$server['is_online']}";
            return $server;
        }, $servers);
    }


}

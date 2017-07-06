<?php
namespace App\Service;

use Psr\Container\ContainerInterface;

class Auth
{
    protected $container;
    protected $uuid;

    function __construct(ContainerInterface $c)
    {
        $this->container = $c;

        $uuid = $c->request->getHeader('Access-UUID');
        $this->uuid = !empty($uuid) ? $uuid[0] : '';
    }

    public function loginRules()
    {
        return [
            'phone' => [
                'label'    => '手机号',
                'required' => true,
            ],
            'password' => [
                'label'    => '密码',
                'required' => true,
            ],
        ];
    }

    // 处理登录请求
    public function handleLogin($input, &$code, &$msg, &$resp)
    {
        if (empty($this->uuid)) {
            $code = -1;
            $msg = 'unknown uuid';

            return;
        }

        $user = $this->container->UserDao->getByPhone($input['phone']);

        if (!$user) {
            $code = -1;
            $msg = "用户不存在";

            return;
        }

        if (md5($input['password'] . $user['salt']) != $user['password']) {
            $code = -1;
            $msg = "密码不正确";

            return;
        }

        $token = $this->signIn($user);

        $resp = ['token' => $token];

        return;
    }

    // 处理退出请求
    public function handleLogout(&$code, &$msg)
    {
        if (empty($this->uuid)) {
            $code = -1;
            $msg = 'unknown uuid';

            return;
        }

        $this->container->AuthCache->logoutByUUID($this->uuid);

        return;
    }

    // 用户登录并返回唯一token
    protected function signIn($data, $duration = 0)
    {
        // 注销上一台设备登录信息
        $this->container->AuthCache->logoutByPhone($data['phone']);

        $loginIP = $_SERVER['REMOTE_ADDR'];
        $loginTime = date('Y-m-d H:i:s');

        $token = md5($data['id'] . $data['phone'] . $loginIP . $loginTime);

        $data['last_login_ip'] = $loginIP;
        $data['last_login_time'] = $loginTime;
        $data['duration'] = $duration;

        $this->container->AuthCache->setLoginData($data['phone'], $this->uuid, $token, $data);

        return $token;
    }
}
?>
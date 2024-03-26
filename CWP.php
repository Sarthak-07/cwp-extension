<?php

namespace App\Extensions\Servers\CWP;

use App\Classes\Extensions\Server;
use App\Helpers\ExtensionHelper;
use Illuminate\Support\Str;

class CWP extends Server
{
    /**
    * Get the extension metadata
    * 
    * @return array
    */
    public function getMetadata()
    {
        return [
            'display_name' => 'CWP',
            'version' => '1.0.0',
            'author' => 'Sarthak',
            'website' => 'https://stellarhost.tech',
        ];
    }
    
    /**
     * Get all the configuration for the extension
     * 
     * @return array
     */
    public function getConfig()
    {
        return [
            [
                'name' => 'hostname',
                'friendlyName' => 'CWP Panel URL',
                'type' => 'text',
                'required' => true,
            ],
            [
                'name' => 'apiKey',
                'friendlyName' => 'API Key',
                'type' => 'text',
                'required' => true,
            ],
        ];
    }

    /**
     * Get product config
     * 
     * @param array $options
     * @return array
     */
    public function getProductConfig($options)
    {
        $hostname = ExtensionHelper::getConfig('CWP', 'hostname');
        $url = $hostname . '/v1/packages';
        $data = [
            'key' => ExtensionHelper::getConfig('CWP', 'apiKey'),
            'action' => 'list'
        ];

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
        $response = curl_exec($ch);
        curl_close($ch);
        if ($response === false) {
            ExtensionHelper::error('CWP', 'Failed to get Packages List: ' . curl_error($ch));
            return false;
        }
        $responseData = json_decode($response, true);
        if ($responseData['status'] !== 'OK') {
            ExtensionHelper::error('CWP', 'Error occurred: ' . $responseData['msj']);
            return false;
        }
        $packages = [];
        foreach ($responseData['msj'] as $package) {
            $packages[] = [
                'name' => $package['package_name'],
                'value' => $package['id']
            ];
        }
        $autoSSLOptions = [
            ['name' => 'Yes', 'value' => '1'],
            ['name' => 'No', 'value' => '0']
        ];
        $resellarOptions = [
            ['name' => 'Yes', 'value' => '1'],
            ['name' => 'No', 'value' => '0']
        ];
        return [
            [
                'name' => 'package',
                'friendlyName' => 'Package Name',
                'type' => 'dropdown',
                'required' => true,
                'options' => $packages,
                'description' => 'Create account with package.',
            ],
            [
                'name' => 'inode',
                'friendlyName' => 'iNode',
                'type' => 'text',
                'required' => true,
                'description' => 'Limit inodes, "0" for unlimited "100" for Default',
            ],
            [
                'name' => 'limit_nproc',
                'friendlyName' => 'Limit nproc',
                'type' => 'text',
                'required' => true,
                'description' => 'Limit number of processes for account, don’t use 0 as it will not allow any processes. Default - "25"',
            ],
            [
                'name' => 'limit_nofile',
                'friendlyName' => 'Limit No File',
                'type' => 'text',
                'required' => true,
                'description' => 'Limit number of open files for account. Default - "100"',
            ],
            [
                'name' => 'server_ips',
                'friendlyName' => 'IP Server',
                'type' => 'text',
                'required' => true,
            ],
            [
                'name' => 'autossl',
                'friendlyName' => 'Auto SSL',
                'type' => 'dropdown',
                'required' => true,
                'options' => $autoSSLOptions,
                'description' => 'Autossl (0 = Not / 1 = Yes)',
            ],
            [
                'name' => 'reseller',
                'friendlyName' => 'Resellar',
                'type' => 'dropdown',
                'required' => true,
                'options' => $resellarOptions,
                'description' => '(1 = To resell, Account Reseller for a Resellers Package, Empty for Standard Package)',
            ],
        ];
    }

    /**
     * Get configurable options for users
     *
     * @param array $options
     * @return array
     */
    public function getUserConfig()
    {
        return [
            [
                'name' => 'password',
                'type' => 'text',
                'friendlyName' => 'Password',
                'required' => true,
            ],
            [
                'name' => 'domain',
                'type' => 'text',
                'friendlyName' => 'Domain',
                'required' => true,
            ],
        ];
    }

    /**
     * Create a server
     * 
     * @param User $user
     * @param array $params
     * @param Order $order
     * @param OrderProduct $orderProduct
     * @param array $configurableOptions
     * @return bool
     */
    public function createServer($user, $params, $order, $orderProduct, $configurableOptions)
    {
        $hostname = ExtensionHelper::getConfig('CWP', 'hostname');
        $apiKey = ExtensionHelper::getConfig('CWP', 'apiKey');
        $package = $configurableOptions['package'] ?? $params['package'];
        $domain = $params['config']['domain'];
        $inode = $configurableOptions['inode'] ?? $params['inode'];
        $limit_nproc = $configurableOptions['limit_nproc'] ?? $params['limit_nproc'];
        $limit_nofile = $configurableOptions['limit_nofile'] ?? $params['limit_nofile'];
        $server_ips = $configurableOptions['server_ips'] ?? $params['server_ips'];
        $autossl = $configurableOptions['autossl'] ?? $params['autossl'];
        $reseller = $configurableOptions['reseller'] ?? $params['reseller'];
        $username = Str::random();
        $password = $params['config']['password'];

        $data = [
            'key' => $apiKey,
            'action' => 'add',
            'domain' => $domain,
            'user' => strtolower($username),
            'pass' => $password,
            'email' => $user->email,
            'package' => $package,
            'inode' => $inode,
            'limit_nproc' => $limit_nproc,
            'limit_nofile' => $limit_nofile,
            'server_ips' => $server_ips,
            'autossl' => $autossl,
        ];
        if ($reseller !== '0') {
            $data['reseller'] = $reseller;
        }
        $url = $hostname . '/v1/account';

        $req = curl_init();
        curl_setopt($req, CURLOPT_URL, $url);
        curl_setopt($req, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($req, CURLOPT_CUSTOMREQUEST, "POST");
        curl_setopt($req, CURLOPT_SSL_VERIFYHOST, FALSE);
        curl_setopt($req, CURLOPT_SSL_VERIFYPEER, FALSE);
        curl_setopt($req, CURLOPT_POSTFIELDS, http_build_query($data));
        $response = curl_exec($req);
        curl_close($req);

        if ($response === false || !($responseData = json_decode($response, true)) || !isset($responseData['status']) || $responseData['status'] !== 'OK') {
            ExtensionHelper::error('CWP', ($response === false ? curl_error($req) : $response));
            return false;
        } else {
            ExtensionHelper::setOrderProductConfig('username', $username, $orderProduct->id);
            return true;
        }
    }

    /**
     * Suspend a server
     * 
     * @param User $user
     * @param array $params
     * @param Order $order
     * @param OrderProduct $orderProduct
     * @param array $configurableOptions
     * @return bool
     */
    public function suspendServer($user, $params, $order, $orderProduct, $configurableOptions)
    {
        $hostname = ExtensionHelper::getConfig('CWP', 'hostname');
        $apiKey = ExtensionHelper::getConfig('CWP', 'apiKey');
        $username = $params['config']['username'];
    
        $data = [
            'key' => $apiKey,
            'action' => 'susp',
            'user' => strtolower($username),
        ];
        $url = $hostname . '/v1/account';
    
        $req = curl_init();
        curl_setopt($req, CURLOPT_URL, $url);
        curl_setopt($req, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($req, CURLOPT_CUSTOMREQUEST, "POST");
        curl_setopt($req, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($req, CURLOPT_POSTFIELDS, http_build_query($data));
        $response = curl_exec($req);
        curl_close($req);
    
        if ($response === false || !($responseData = json_decode($response, true)) || !isset($responseData['status']) || $responseData['status'] !== 'OK') {
            ExtensionHelper::error('CWP', ($response === false ? curl_error($req) : $response));
            return false;
        } else {
            return true;
        }
    }

    /**
     * Unsuspend a server
     * 
     * @param User $user
     * @param array $params
     * @param Order $order
     * @param OrderProduct $orderProduct
     * @param array $configurableOptions
     * @return bool
     */
    public function unsuspendServer($user, $params, $order, $orderProduct, $configurableOptions)
    {
        $hostname = ExtensionHelper::getConfig('CWP', 'hostname');
        $apiKey = ExtensionHelper::getConfig('CWP', 'apiKey');
        $username = $params['config']['username'];
    
        $postData = [
            'key' => $apiKey,
            'action' => 'unsp',
            'user' => strtolower($username),
        ];
        $url = $hostname . '/v1/account';
    
        $req = curl_init();
        curl_setopt($req, CURLOPT_URL, $url);
        curl_setopt($req, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($req, CURLOPT_CUSTOMREQUEST, "POST");
        curl_setopt($req, CURLOPT_SSL_VERIFYHOST, FALSE);
        curl_setopt($req, CURLOPT_SSL_VERIFYPEER, FALSE);
        curl_setopt($req, CURLOPT_POSTFIELDS, http_build_query($postData));
        $response = curl_exec($req);
        curl_close($req);
    
        if ($response === false || !($responseData = json_decode($response, true)) || !isset($responseData['status']) || $responseData['status'] !== 'OK') {
            ExtensionHelper::error('CWP', ($response === false ? curl_error($req) : $response));
            return false;
        } else {
            return true;
        }
    }

    /**
     * Terminate a server
     * 
     * @param User $user
     * @param array $params
     * @param Order $order
     * @param OrderProduct $orderProduct
     * @param array $configurableOptions
     * @return bool
     */
    public function terminateServer($user, $params, $order, $orderProduct, $configurableOptions)
    {
        $hostname = ExtensionHelper::getConfig('CWP', 'hostname');
        $apiKey = ExtensionHelper::getConfig('CWP', 'apiKey');
        $username = $params['config']['username'];
        $email = $user->email;
    
        $postData = [
            'key' => $apiKey,
            'action' => 'del',
            'user' => strtolower($username),
            'email' => $email,
        ];
        $url = $hostname . '/v1/account';
    
        $req = curl_init();
        curl_setopt($req, CURLOPT_URL, $url);
        curl_setopt($req, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($req, CURLOPT_CUSTOMREQUEST, "POST");
        curl_setopt($req, CURLOPT_SSL_VERIFYHOST, FALSE);
        curl_setopt($req, CURLOPT_SSL_VERIFYPEER, FALSE);
        curl_setopt($req, CURLOPT_POSTFIELDS, http_build_query($postData));
        $response = curl_exec($req);
        curl_close($req);
    
        return true;
    }

    public function getLink($user, $params, $order, $orderProduct, $configurableOptions)
    {
        $username = $params['config']['username'];
        $apiKey = ExtensionHelper::getConfig('CWP', 'apiKey');
        $hostname = ExtensionHelper::getConfig('CWP', 'hostname');

        $data = [
            'key' => $apiKey,
            'action' => 'list',
            'user' => strtolower($username),
            'timer' => 60, // Expiration time of the session in minutes (adjust as needed)
        ];
        $url = $hostname . '/v1/user_session';

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
        curl_setopt($ch, CURLOPT_POST, 1);
        $response = curl_exec($ch);
        curl_close($ch);

        $responseData = json_decode($response, true);

        $autoLoginUrl = $responseData['msj']['details'][0]['url'];
        return $autoLoginUrl;
    }
}
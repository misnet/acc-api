<?php
namespace Kuga\Api\Acc;
use Kuga\Module\Acc\Model\Oauth2Model;
class Oauth extends BaseApi{
    public function createAuth(){

        // init configuration
        $clientID = '1080695414532-f1rv91ka4kcbkaivin0qrg7m163jt1eb.apps.googleusercontent.com';
        $clientSecret = 'UxXwE3MIka4FvVjGLc_i5jKh';
        $redirectUri = 'http://wms.api.kuga.wang/redirect.php';
// create Client Request to access Google API
        $client = new \Google_Client();
        $client->setClientId($clientID);
        $client->setClientSecret($clientSecret);
        $client->setRedirectUri($redirectUri);
        $client->addScope("email");
        $client->addScope("profile");
        $client->addScope('https://mail.google.com/');//您必须声明要访问的范围，例如-Gmail的最高特权范围，该范围还允许脱机访问
// authenticate code from Google OAuth Flow
        if ($this->request->get('code')){
            $token = $client->fetchAccessTokenWithAuthCode($this->request->get('code'));
            $client->setAccessToken($token['access_token']);
            // get profile info
            $google_oauth = new \Google_Service_Oauth2($client);
            $google_account_info = $google_oauth->userinfo->get();
            $data['email'] =  $google_account_info->email;
            $data['name'] =  $google_account_info->name;
            $data['oauthId']=  $google_account_info->id;
            //替换系统自己的逻辑
//            print_r($google_account_info);
            $oauth2Model = new Oauth2Model();
            return $oauth2Model->creatOrUpdateUser($data);
            // now you can use this profile info to create account in your website and make user logged in.
        } else {
            echo "<a href='".$client->createAuthUrl()."'>Google Login</a>";
        }
        exit;
    }
}
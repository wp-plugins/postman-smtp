<?php

class OAuthWrapHandler
{

    public function processRequest()
    {
        //Clear existing cookies
        $this->expireCookies();
        //Parse response from authentication request
        $cookies_setup = $this->convertParamsToCookies($_REQUEST);
        //Get access token
        if(isset($cookies_setup['verification_code']))
        {
            //request the access token
            $auth_params = $this->getAuthorizationToken(
                    WRAP_ACCESS_URL
                    , WRAP_CLIENT_ID
                    , WRAP_CLIENT_SECRET
                    , WRAP_CALLBACK
                    , $cookies_setup['verification_code']
            );
            
            //remove the code from the output cookies so the users
            //don't know what the gatekey is.
            unset($cookies_setup['verification_code']);
        }
        else
        {
            throw new Exception("No verification Code returned from Windows Live Services.");
        }

        $cookies_auth = $this->convertParamsToCookies($auth_params);
        $cookies = array_merge($cookies_setup, $cookies_auth);
        $this->setAuthCookies($cookies);
    }

    private function expireCookies()
    {
        setcookie ("c_accessToken", "", time() - 3600);
        setcookie ("c_clientId", "", time() - 3600);
        setcookie ("c_clientState", "", time() - 3600);
        setcookie ("c_scope", "", time() - 3600);
        setcookie ("c_error", "", time() - 3600);
        setcookie ("c_uid", "", time() - 3600);
        setcookie ("c_expiry", "", time() - 3600);
        setcookie ("lca", "", time() - 3600);
    }

    private function setAuthCookies($cookies)
    {
        foreach($cookies as $key => $value)
        {
            setcookie ($key, $value, time() + 3600*24);
        }
        setcookie ('c_clientId', WRAP_CLIENT_ID, time() + 3600*24); //clientID == appId
        setcookie ('lca', 'done', time() + 3600*24); 
    }

    private function convertParamsToCookies($array)
    {
        $cookies = array();

        foreach(array_keys($array) as $getParam)
        {
            $getParam = urldecode($getParam);
            switch($getParam)
            {
                case 'wrap_client_state':
                    //if(strrpos($array['wrap_client_state'], 'js_close_window') >= 0)
                    $cookies['c_clientState'] = $array['wrap_client_state'];
                    break;
                case 'wrap_verification_code':
                    $cookies['verification_code'] = $array['wrap_verification_code'];
                    break;
                case 'exp': //scope
                    $cookies['c_scope'] = str_replace(';', ',',$array['exp']);
                    break;
                case 'error_code':
                    $cookies['c_error'] = ' ' . $array['error_code'];
                    break;
                case 'wrap_error_reason':
                    $cookies['c_error'] = ' ' . $array['wrap_error_reason'];
                    break;
                case 'wrap_access_token':
                    $cookies['c_accessToken'] = $array['wrap_access_token'];
                    break;
                case 'wrap_access_token_expires_in':
                    $cookies['c_expiry'] = date('j/m/Y g:i:s A', $array['wrap_access_token_expires_in']);
                    break;
                case 'uid':
                    $cookies['c_uid'] = $array['uid'];
                    break;
            }
        }
        return $cookies;
    }

    private function getAuthorizationToken($authUrl, $appId, $appSecret, $callbackUrl, $verificationCode)
    {
        //Using the returned verification code build a query to the
        //authorization url that will return the authorized token.

        $tokenRequest = 'wrap_client_id=' . urlencode($appId)
                . '&wrap_client_secret=' . urlencode($appSecret)
                . '&wrap_callback=' . urlencode($callbackUrl)
                . '&wrap_verification_code=' . urlencode($verificationCode);
        $response = $this->postWRAPRequest($authUrl, $tokenRequest);
        return $this->parseWRAPResponse($response);
    }

    private function postWRAPRequest($posturl, $postvars)
    {
        $ch = curl_init($posturl);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $postvars);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
        curl_setopt($ch, CURLOPT_HEADER, 1);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        $Rec_Data = curl_exec($ch);
        curl_close($ch);

        return urldecode($Rec_Data);
    }

    private function parseWRAPResponse($response)
    {
        //First remove any extraneous header information from the returned POST variables
        $pos = strpos($response, 'wrap_access_token=');
        if ($pos === false)
        {
            $pos = strpos($response, 'wrap_error_reason=');
        }
        $codes = '?' . substr($response, $pos, strlen($response));

        //RegEx the string to separate out the variables and their values
        if (preg_match_all('/[?&]([^&=]+)=([^&=]+)/', $codes, $matches))
        {
            for($i =0; $i < count($matches[1]); $i++)
            {
                //The first element in the matches array is the combination
                //of both matches.
                $contents[$matches[1][$i]] = $matches[2][$i];
            }
        }
        else
        {
            throw new Exception('No matches for regular expression.');
        }
        return $contents;
    }
}
?>

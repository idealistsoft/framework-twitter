<?php

namespace app\twitter;

use infuse\View;
use app\users\models\User;
use app\twitter\models\TwitterProfile;
use app\twitter\libs\TwitterService;

class Controller
{
    use \InjectApp;

    public static $properties = [
        'models' => [ 'TwitterProfile' ],
    ];

    public static $scaffoldAdmin;

    private $twitter;

    public function middleware($req, $res)
    {
        // add routes
        $this->app->get('/twitter/connect', 'connect')
                  ->get('/twitter/callback', 'callback')
                  ->post('/twitter/disconnect', 'disconnect');

        $this->app[ 'twitter' ] = function ($c) {
            return new \TwitterOAuth\Api(
                $c[ 'config' ]->get('twitter.consumerKey'),
                $c[ 'config' ]->get('twitter.consumerSecret'));
        };

        $this->app[ 'twitter_service' ] = function ($c) {
            return new TwitterService($c);
        };
    }

    public function twitter($oauthToken = null, $oauthTokenSecret = null, $cache = true)
    {
        if ($this->twitter && $cache) {
            return $this->twitter;
        }

        $this->twitter = new \TwitterOAuth\Api(
            $this->app[ 'config' ]->get('twitter.consumerKey'),
            $this->app[ 'config' ]->get('twitter.consumerSecret'),
            $oauthToken,
            $oauthTokenSecret
        );

        return $this->twitter;
    }

    public function connect($req, $res)
    {
        $twitter = $this->app[ 'twitter' ];

        $callbackUrl = $this->app[ 'config' ]->get('twitter.callbackUrl');
        if ($req->query('forceLogin')) {
            $callbackUrl .= '?forceLogin=t';
        }

        /* Get temporary credentials. */
        $request_token = $twitter->getRequestToken($callbackUrl);

        /* Save temporary credentials to session. */
        $req->setSession([
            'oauth_token' => $request_token[ 'oauth_token' ],
            'oauth_token_secret' => $request_token[ 'oauth_token_secret' ], ]);

        if ($twitter->http_code == 200) {
            $res->redirect($twitter->getAuthorizeURL($request_token[ 'oauth_token' ]));
        } else {
            $res->setCode(500);
        }
    }

    public function disconnect($req, $res)
    {
        $currentUser = $this->app[ 'user' ];

        if ($currentUser->isLoggedIn() || $currentUser->twitterConnected()) {
            $currentUser->set('twitter_id', null);
        }

        $redir = '/profile';
        if ($req->query('r')) {
            $redir = $req->query('r');
        }

        $res->redirect($redir);
    }

    public function callback($req, $res)
    {
        if ($req->query('denied')) {
            return $res->redirect('/');
        }

        $twitter = $this->twitter($req->session('oauth_token'), $req->session('oauth_token_secret'));

        $token_credentials = $twitter->getAccessToken($req->query('oauth_verifier'));

        if (!isset($token_credentials[ 'oauth_token' ])) {
            $this->app[ 'errors' ]->push([
                'context' => 'user.login',
                'error' => 'invalid_token',
                'message' => 'Twitter: Invalid token. Please try again.',
            ]);

            $usersController = new \app\users\Controller();
            $usersController->injectApp($this->app);

            return $usersController->loginForm($req, $res);
        }

        $twitter = $this->twitter($token_credentials[ 'oauth_token' ], $token_credentials[ 'oauth_token_secret' ], false);

        // fetch profile

        $user_profile = $twitter->get('account/verify_credentials');

        if (isset($user_profile->errors)) {
            return $res->setBody('There was an error signing you into Twitter:<br/><pre>'.print_r($user_profile->errors, true).'</pre>');
        }

        /* log the user in or kick off signup */

        $currentUser = $this->app[ 'user' ];

        $tid = $user_profile->id;

        // generate parameters to update profile
        $user_profile = (array) json_decode(json_encode($user_profile), true);
        $profileUpdateArray = [
            'id' => $tid,
            'access_token' => $token_credentials[ 'oauth_token' ],
            'access_token_secret' => $token_credentials[ 'oauth_token_secret' ], ];

        // twitter id matches existing user?
        $users = User::find([
            'where' => [
                'twitter_id' => $tid, ], ]);

        if ($users[ 'count' ] == 1) {
            $user = $users[ 'models' ][ 0 ];

            // check if we are dealing with a temporary user
            if (!$user->isTemporary()) {
                if ($user->id() != $currentUser->id()) {
                    if ($req->query('forceLogin') || !$currentUser->isLoggedIn()) {
                        // log the user in
                        $this->app[ 'auth' ]->signInUser($user->id(), 'twitter');
                    } else {
                        // inform the user that the twitter account they are trying to
                        // connect belongs to someone else
                        return new View('switchingAccounts/twitter', [
                            'title' => 'Switch accounts?',
                            'otherUser' => $user,
                            'otherProfile' => $user->twitterProfile(), ]);
                    }
                }

                $profile = new TwitterProfile($tid);

                // create or update the profile
                if ($profile->exists()) {
                    $profile->set($profileUpdateArray);
                } else {
                    $profile = new TwitterProfile();
                    $profile->create($profileUpdateArray);
                }

                // refresh profile from API
                $profile->refreshProfile($user_profile);

                return $this->finalRedirect($req, $res);
            } else {
                // show finish signup screen
                $req->setSession('tid', $tid);

                return $res->redirect('/signup/finish');
            }
        }

        if ($currentUser->isLoggedIn()) {
            // add to current user's account
            $currentUser->set('twitter_id', $tid);
        } else {
            // save this for later
            $req->setSession('tid', $tid);
        }

        $profile = new TwitterProfile($tid);

        // create or update the profile
        if ($profile->exists()) {
            $profile->set($profileUpdateArray);
        } else {
            $profile = new TwitterProfile();
            $profile->create($profileUpdateArray);
        }

        // refresh profile from API
        $profile->refreshProfile($user_profile);

        // get outta here
        if ($currentUser->isLoggedIn()) {
            $this->finalRedirect($req, $res);
        } else {
            $res->redirect('/signup/finish');
        }
    }

    private function finalRedirect($req, $res)
    {
        if ($redirect = $req->cookies('redirect')) {
            $req->setCookie('redirect', '', time() - 86400, '/');
            $res->redirect($redirect);
        } elseif ($redirect = $req->query('redir')) {
            $res->redirect($redirect);
        } else {
            $res->redirect('/profile');
        }
    }

    public function refreshProfiles()
    {
        return TwitterProfile::refreshProfiles();
    }
}

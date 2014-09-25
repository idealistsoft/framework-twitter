<?php

namespace app\twitter\libs;

use Pimple\Container;

use app\twitter\models\TwitterProfile;

class TwitterService
{
    private $app;
    private $profile;

    public function __construct(Container $app)
    {
        $this->app = $app;
    }

    /**
	 * Sets the appropriate Twitter API access token using
	 * a given Twitter Profile
	 *
	 * @param TwitterProfile $profile
     *
     * @return TwitterService
	 */
    public function setAccessTokenFromProfile(TwitterProfile $profile)
    {
        $tokens = $profile->get(['access_token', 'access_token_secret']);

        if (!empty($tokens['access_token'])) {
            $this->app['twitter']->setTokens($tokens['access_token'], $tokens['access_token_secret']);
            $this->profile = $profile;
        } else {
            // use the access token from profile that referenced this profile
            $referencingProfile = $profile->relation('most_recently_referenced_by');

            // recursion would be nice here, but could be dangerous
            $tokens = $referencingProfile->get(['access_token', 'access_token_secret']);

            if ($referencingProfile->exists() && !empty($tokens['access_token'])) {
                $this->app['twitter']->setTokens($tokens['access_token'], $tokens['access_token_secret']);
                $this->profile = $referencingProfile;
            }
        }

        return $this;
    }

    /**
	 * Performs an API call on the twitter API (if available) or
	 * returns a mock response
	 *
	 * @param string $endpoint
	 * @param string $method HTTP method
	 * @param array $params optional params
	 *
	 * @return object
	 */
    public function api($endpoint, $method, $params = null)
    {
        $method = strtolower($method);

        try {
            $response = $this->app['twitter']->$method($endpoint, $params);
        } catch (\Exception $e) {
            $this->app['logger']->error($e);

            return false;
        }

        // log certain errors like rate limiting or expired tokens
        if (is_object($response) && property_exists($response, 'errors')) {
            foreach ($response->errors as $error) {
                if ($error->code == 88)
                    $this->app['logger']->error("Hit Twitter rate limit on $endpoint with params: " . json_encode($params));
                elseif ($error->code == 89) { // expired access token
                    // clear the access token of the user's profile
                    if ($this->profile) {
                        $this->profile->grantAllPermissions();
                        $this->profile->set(['access_token' => '', 'access_token_secret' => '']);
                        $this->profile->enforcePermissions();
                    }

                    // $this->app[ 'logger' ]->error( "Twitter access token expired on $endpoint with params: " . json_encode( $params ) );
                } elseif ($error->code == 231)
                    $this->app['logger']->error("User must verify twitter login on $endpoint with params: " . json_encode($params));
            }
        }

        return $response;
    }
}

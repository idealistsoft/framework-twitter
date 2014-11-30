<?php

namespace app\twitter\models;

use app\social\models\SocialMediaProfile;

class TwitterProfile extends SocialMediaProfile
{
    public static $properties = [
        'id' => [
            'type' => 'number',
            'admin_hidden_property' => true,
        ],
        'username' => [
            'type' => 'string',
            'admin_html' => '<a href="http://twitter.com/{username}" target="_blank">{username}</a>',
            'searchable' => true,
        ],
        'name' => [
            'type' => 'string',
            'searchable' => true,
        ],
        'access_token' => [
            'type' => 'string',
            'admin_type' => 'password',
            'admin_hidden_property' => true,
        ],
        'access_token_secret' => [
            'type' => 'string',
            'admin_type' => 'password',
            'admin_hidden_property' => true,
        ],
        'profile_image_url' => [
            'type' => 'string',
            'null' => true,
            'admin_html' => '<a href="{profile_image_url}" target="_blank"><img src="{profile_image_url}" alt="Profile Image" class="img-circle" /></a>',
            'admin_truncate' => false,
            'admin_hidden_property' => true,
        ],
        'description' => [
            'type' => 'string',
            'null' => true,
            'admin_nowrap' => false,
            'admin_hidden_property' => true,
        ],
        'location' => [
            'type' => 'string',
            'null' => true,
            'admin_hidden_property' => true,
        ],
        'friends_count' => [
            'type' => 'number',
            'null' => true,
            'admin_hidden_property' => true,
        ],
        'followers_count' => [
            'type' => 'number',
            'null' => true,
            'admin_hidden_property' => true,
        ],
        'listed_count' => [
            'type' => 'number',
            'null' => true,
            'admin_hidden_property' => true,
        ],
        'favourites_count' => [
            'type' => 'number',
            'null' => true,
            'admin_hidden_property' => true,
        ],
        'statuses_count' => [
            'type' => 'number',
            'null' => true,
            'admin_hidden_property' => true,
        ],
        'verified' => [
            'type' => 'boolean',
            'default' => false,
            'admin_hidden_property' => true,
        ],
        // the last date the profile was refreshed from twitter
        'last_refreshed' => [
            'type' => 'date',
            'admin_hidden_property' => true,
        ],
        // the twitter id that most recently referenced this profile
        // so that we can use their access token to refresh the profile
        // i.e. from a retweet
        'most_recently_referenced_by' => [
            'type' => 'number',
            'null' => true,
            'relation' => '\\app\\twitter\\models\\TwitterProfile',
            'admin_hidden_property' => true,
        ],
    ];

    public static $apiPropertyMapping = [
        'username' => 'screen_name',
        'name' => 'name',
        'profile_image_url' => 'profile_image_url',
        'description' => 'description',
        'location' => 'location',
        'friends_count' => 'friends_count',
        'followers_count' => 'followers_count',
        'listed_count' => 'listed_count',
        'favourites_count' => 'favourites_count',
        'statuses_count' => 'statuses_count',
        'verified' => 'verified', ];

    public function userPropertyForProfileId()
    {
        return 'twitter_id';
    }

    public function apiPropertyMapping()
    {
        return [
            'username' => 'screen_name',
            'name' => 'name',
            'profile_image_url' => 'profile_image_url',
            'description' => 'description',
            'location' => 'location',
            'friends_count' => 'friends_count',
            'followers_count' => 'followers_count',
            'listed_count' => 'listed_count',
            'favourites_count' => 'favourites_count',
            'statuses_count' => 'statuses_count',
            'verified' => 'verified'
        ];
    }

    public function daysUntilStale()
    {
        return 7;
    }

    public function numProfilesToRefresh()
    {
        return 180;
    }

    public function url()
    {
        $username = $this->username;

        return ($username) ? 'http://twitter.com/'.$username : '';
    }

    public function profilePicture($size = 80)
    {
        return str_replace('_normal', '_bigger', $this->profile_image_url);
    }

    public function isLoggedIn()
    {
        $twitter = $this->app[ 'twitter_service' ];
        $twitter->setAccessTokenFromProfile($this);

        $result = $twitter->api('account/verify_credentials', 'get');

        if ($result && property_exists($result, 'id') && $result->id == $this->id()) {
            return true;
        }

        if (property_exists($result, 'errors')) {
            $this->app[ 'logger' ]->error('Could not authenticate profile # '.$this->id().': '.json_encode($result->errors));
        }

        return false;
    }

    public function getProfileFromApi()
    {
        $twitter = $this->app[ 'twitter_service' ];
        $twitter->setAccessTokenFromProfile($this);

        $profile = $twitter->api('users/show', 'get', [  'user_id' => $this->id() ]);

        if (!is_object($profile)) {
            return false;
        }

        if (property_exists($profile, 'errors')) {
            return false;
        }

        return (array) json_decode(json_encode($profile), true);
    }
}
